<?php

declare(strict_types=1);

use App\Controllers\EquipmentController;
use App\Http\Request;
use App\Repository\AuditLogRepository;
use App\Repository\EquipmentHistoryRepository;
use App\Repository\EquipmentRepository;
use App\Repository\ServicePlanRepository;
use App\Repository\VehicleDetailsRepository;
use App\Services\EquipmentService;
use App\Services\QrCodeService;
use App\Services\QrService;
use PDO;
use Mockery as m;

beforeEach(function (): void {
    $pdo = m::mock(PDO::class);

    $this->equipmentRepository = m::mock(EquipmentRepository::class, [$pdo]);
    $this->vehicleDetailsRepository = m::mock(VehicleDetailsRepository::class, [$pdo]);
    $this->servicePlanRepository = m::mock(ServicePlanRepository::class, [$pdo]);
    $this->historyRepository = m::mock(EquipmentHistoryRepository::class, [$pdo]);
    $this->auditLogRepository = m::mock(AuditLogRepository::class, [$pdo]);
    $this->auditLogRepository->shouldReceive('logFromRequest')->byDefault();
    $this->historyRepository->shouldReceive('add')->byDefault();

    $this->equipmentService = new EquipmentService(
        $this->equipmentRepository,
        $this->vehicleDetailsRepository,
        $this->servicePlanRepository,
        $this->historyRepository,
        $this->auditLogRepository,
    );
    $this->qrService = new QrService(
        $this->equipmentRepository,
        m::mock(\App\Repository\IncidentRepository::class, [$pdo]),
        m::mock(\App\Repository\DailyVehicleReportRepository::class, [$pdo]),
        new QrCodeService(),
        'http://localhost:4200',
    );
    $this->equipmentController = new EquipmentController($this->equipmentService, $this->qrService);
});

afterEach(function (): void {
    m::close();
});

// --- LIST (index) ---

it('index returns paginated list', function (): void {
    $filters = ['nazwa' => '', 'kategoria' => '', 'numer_seryjny' => '', 'ostatni_przebieg' => '', 'employee_id' => '', 'terminal_id' => '', 'is_active' => '', 'sort' => 'id', 'direction' => 'asc'];
    $this->equipmentRepository->shouldReceive('search')
        ->with($filters, 25, 0, 'id', 'asc')
        ->andReturn([
            ['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'Ford Transit', 'numer_seryjny' => 'FT-001', 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'ostatni_przebieg' => 125000, 'is_active' => 1, 'created_at' => null, 'updated_at' => null],
        ]);
    $this->equipmentRepository->shouldReceive('countSearch')->with($filters)->andReturn(1);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['total'])->toBe(1);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['nazwa'])->toBe('Ford Transit');
    expect($response->data()['data'][0]['ostatni_przebieg'])->toBe(125000);
});

it('index applies filters from query string', function (): void {
    $filters = ['nazwa' => 'Ford', 'kategoria' => 'pojazd', 'numer_seryjny' => '', 'ostatni_przebieg' => '', 'employee_id' => '', 'terminal_id' => '', 'is_active' => '1', 'sort' => 'id', 'direction' => 'asc'];
    $this->equipmentRepository->shouldReceive('search')->with($filters, 10, 0, 'id', 'asc')->andReturn([]);
    $this->equipmentRepository->shouldReceive('countSearch')->with($filters)->andReturn(0);

    $request = new Request(query: ['nazwa' => 'Ford', 'kategoria' => 'pojazd', 'is_active' => '1', 'per_page' => '10'], body: [], headers: []);
    $response = $this->equipmentController->index($request);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['per_page'])->toBe(10);
});

// --- STORE (create) ---

it('store returns 422 when name missing', function (): void {
    $request = new Request(query: [], body: ['kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 422 for invalid category', function (): void {
    $request = new Request(query: [], body: ['nazwa' => 'Test', 'kategoria' => 'nieznana'], headers: []);
    $response = $this->equipmentController->store($request);
    expect($response->statusCode())->toBe(422);
});

it('store returns 409 for duplicate name', function (): void {
    $this->equipmentRepository->shouldReceive('findByName')->with('Ford Transit')->andReturn(['id' => 5]);

    $request = new Request(query: [], body: ['nazwa' => 'Ford Transit', 'kategoria' => 'pojazd'], headers: []);
    $response = $this->equipmentController->store($request);
    expect($response->statusCode())->toBe(409);
});

it('store creates equipment and returns 201', function (): void {
    $this->equipmentRepository->shouldReceive('findByName')->with('Wózek')->andReturnNull();
    $this->equipmentRepository->shouldReceive('createEquipment')
        ->with(m::on(fn (array $d): bool => $d['kategoria'] === 'inne' && $d['nazwa'] === 'Wózek' && $d['is_active'] === true))
        ->andReturn(['id' => 7, 'kategoria' => 'inne', 'nazwa' => 'Wózek', 'numer_seryjny' => null, 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);

    $request = new Request(query: [], body: ['nazwa' => 'Wózek', 'kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['nazwa'])->toBe('Wózek');
    expect($response->data()['vehicle_details'])->toBeNull();
});

it('store creates vehicle equipment with vehicle_details', function (): void {
    $this->equipmentRepository->shouldReceive('findByName')->with('Ford')->andReturnNull();
    $this->equipmentRepository->shouldReceive('createEquipment')
        ->andReturn(['id' => 8, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => 'FT-1', 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'ostatni_przebieg' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);
    $this->vehicleDetailsRepository->shouldReceive('createDetails')
        ->with(m::on(fn (array $d): bool => $d['equipment_id'] === 8 && $d['ostatni_przebieg'] === 120000))
        ->andReturn(['equipment_id' => 8, 'ostatni_przebieg' => 120000, 'ostatni_serwis_olejowy' => null, 'ostatnia_awaria' => null, 'data_ostatniej_oc' => null, 'wynik_ostatniej_oc' => null]);

    $request = new Request(query: [], body: ['nazwa' => 'Ford', 'kategoria' => 'pojazd', 'ostatni_przebieg' => 120000], headers: []);
    $response = $this->equipmentController->store($request);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['vehicle_details']['ostatni_przebieg'])->toBe(120000);
});

// --- SHOW (get) ---

it('show returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->show($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('show returns equipment with vehicle details, service plans and timeline', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'Ford', 'numer_seryjny' => 'FT-1', 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'ostatni_przebieg' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);
    $this->vehicleDetailsRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn(['equipment_id' => 1, 'ostatni_przebieg' => 1000, 'ostatni_serwis_olejowy' => null, 'ostatnia_awaria' => null, 'data_ostatniej_oc' => null, 'wynik_ostatniej_oc' => null]);
    $this->servicePlanRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn([['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'interwal_km' => 15000, 'interwal_dni' => null, 'data_ostatniego_wykonania' => null, 'data_nastepnego_planowanego' => null, 'is_active' => 1]]);
    $this->historyRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn([['id' => 1, 'equipment_id' => 1, 'typ' => 'przypisanie', 'opis' => 'Utworzono', 'data' => '2026-01-01', 'created_by' => null]]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->show($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['vehicle_details']['ostatni_przebieg'])->toBe(1000);
    expect($response->data()['service_plans'])->toHaveCount(1);
    expect($response->data()['timeline'])->toHaveCount(1);
});

// --- UPDATE ---

it('update returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(5)->andReturnNull();

    $request = new Request(query: [], body: ['nazwa' => 'T', 'kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->update($request, ['id' => '5']);
    expect($response->statusCode())->toBe(404);
});

it('update returns 422 when name missing', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'is_active' => 1]);

    $request = new Request(query: [], body: ['kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('update returns 409 for duplicate name (different id)', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'is_active' => 1]);
    $this->equipmentRepository->shouldReceive('findByName')->with('Inny')->andReturn(['id' => 2]);

    $request = new Request(query: [], body: ['nazwa' => 'Inny', 'kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->update($request, ['id' => '1']);
    expect($response->statusCode())->toBe(409);
});

it('update upserts vehicle details when category is pojazd', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);
    $this->equipmentRepository->shouldReceive('findByName')->with('T')->andReturn(['id' => 1]);
    $this->equipmentRepository->shouldReceive('updateEquipment')->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'numer_seryjny' => null, 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'ostatni_przebieg' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);
    $this->vehicleDetailsRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn(['equipment_id' => 1, 'ostatni_przebieg' => 500, 'ostatni_serwis_olejowy' => null, 'ostatnia_awaria' => null, 'data_ostatniej_oc' => null, 'wynik_ostatniej_oc' => null]);
    $this->vehicleDetailsRepository->shouldReceive('updateDetails')
        ->with(1, m::on(fn (array $d): bool => $d['ostatni_przebieg'] === 9999))
        ->andReturn(['equipment_id' => 1, 'ostatni_przebieg' => 9999, 'ostatni_serwis_olejowy' => null, 'ostatnia_awaria' => null, 'data_ostatniej_oc' => null, 'wynik_ostatniej_oc' => null]);

    $request = new Request(query: [], body: ['nazwa' => 'T', 'kategoria' => 'pojazd', 'ostatni_przebieg' => 9999], headers: []);
    $response = $this->equipmentController->update($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['vehicle_details']['ostatni_przebieg'])->toBe(9999);
});

it('update deletes vehicle details when category changes to inne', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);
    $this->equipmentRepository->shouldReceive('findByName')->with('T')->andReturn(['id' => 1]);
    $this->equipmentRepository->shouldReceive('updateEquipment')->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'numer_seryjny' => null, 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'ostatni_przebieg' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);
    $this->vehicleDetailsRepository->shouldReceive('deleteDetails')->with(1)->andReturn(true);

    $request = new Request(query: [], body: ['nazwa' => 'T', 'kategoria' => 'inne'], headers: []);
    $response = $this->equipmentController->update($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['vehicle_details'])->toBeNull();
});

// --- DESTROY ---

it('delete returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->destroy($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('delete removes equipment and returns success', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'is_active' => 1]);
    $this->equipmentRepository->shouldReceive('delete')->with(1)->andReturn(true);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->destroy($request, ['id' => '1']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});

// --- ASSIGN ---

it('assign returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: ['current_employee_id' => 2], headers: []);
    $response = $this->equipmentController->assign($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('assign returns 422 when no assignment fields provided', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'is_active' => 1]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->assign($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('assign updates employee and returns updated equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'current_employee_id' => null, 'employee_imie' => null, 'employee_nazwisko' => null, 'current_terminal_id' => null, 'terminal_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);
    $this->equipmentRepository->shouldReceive('updateAssignment')
        ->with(1, m::on(fn (array $d): bool => $d['current_employee_id'] === 5))
        ->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'current_employee_id' => 5, 'employee_imie' => 'Jan', 'employee_nazwisko' => 'Kowalski', 'current_terminal_id' => null, 'terminal_nazwa' => null, 'is_active' => 1, 'created_at' => null, 'updated_at' => null]);

    $request = new Request(query: [], body: ['current_employee_id' => 5], headers: []);
    $response = $this->equipmentController->assign($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['current_employee_id'])->toBe(5);
    expect($response->data()['employee_name'])->toBe('Jan Kowalski');
});

// --- TIMELINE ---

it('timeline returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->timeline($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('timeline returns history events', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'inne', 'nazwa' => 'T', 'is_active' => 1]);
    $this->historyRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn([
        ['id' => 1, 'equipment_id' => 1, 'typ' => 'przypisanie', 'opis' => 'Utworzono', 'data' => '2026-01-01', 'created_by' => null],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->timeline($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['typ'])->toBe('przypisanie');
});

// --- SERVICE PLANS ---

it('listServicePlans returns 404 for missing equipment', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->listServicePlans($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('listServicePlans returns plans', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);
    $this->servicePlanRepository->shouldReceive('findByEquipmentId')->with(1)->andReturn([
        ['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'interwal_km' => 15000, 'interwal_dni' => null, 'data_ostatniego_wykonania' => null, 'data_nastepnego_planowanego' => null, 'is_active' => 1],
    ]);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->listServicePlans($request, ['id' => '1']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['data'])->toHaveCount(1);
    expect($response->data()['data'][0]['interwal_km'])->toBe(15000);
});

it('createServicePlan returns 422 when typ_przegladu missing', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);

    $request = new Request(query: [], body: ['interwal_km' => 10000], headers: []);
    $response = $this->equipmentController->createServicePlan($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createServicePlan returns 422 for invalid km interval', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);

    $request = new Request(query: [], body: ['typ_przegladu' => 'olejowy', 'interwal_km' => 'abc'], headers: []);
    $response = $this->equipmentController->createServicePlan($request, ['id' => '1']);
    expect($response->statusCode())->toBe(422);
});

it('createServicePlan returns 201 and computes next planned date', function (): void {
    $this->equipmentRepository->shouldReceive('findById')->with(1)->andReturn(['id' => 1, 'kategoria' => 'pojazd', 'nazwa' => 'T', 'is_active' => 1]);
    $this->servicePlanRepository->shouldReceive('createPlan')
        ->with(m::on(fn (array $d): bool => $d['equipment_id'] === 1 && $d['typ_przegladu'] === 'olejowy' && $d['interwal_dni'] === 365))
        ->andReturn(['id' => 9, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'interwal_km' => null, 'interwal_dni' => 365, 'data_ostatniego_wykonania' => '2026-01-01', 'data_nastepnego_planowanego' => '2027-01-01', 'is_active' => 1]);

    $request = new Request(query: [], body: ['typ_przegladu' => 'olejowy', 'interwal_dni' => 365, 'data_ostatniego_wykonania' => '2026-01-01'], headers: []);
    $response = $this->equipmentController->createServicePlan($request, ['id' => '1']);

    expect($response->statusCode())->toBe(201);
    expect($response->data()['data_nastepnego_planowanego'])->toBe('2027-01-01');
});

it('updateServicePlan returns 404 for missing plan', function (): void {
    $this->servicePlanRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: ['typ_przegladu' => 'olejowy'], headers: []);
    $response = $this->equipmentController->updateServicePlan($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('updateServicePlan returns 422 when typ_przegladu missing', function (): void {
    $this->servicePlanRepository->shouldReceive('findById')->with(3)->andReturn(['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'is_active' => 1]);

    $request = new Request(query: [], body: ['interwal_km' => 10000], headers: []);
    $response = $this->equipmentController->updateServicePlan($request, ['id' => '3']);
    expect($response->statusCode())->toBe(422);
});

it('updateServicePlan updates and returns 200', function (): void {
    $this->servicePlanRepository->shouldReceive('findById')->with(3)->andReturn(['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'interwal_km' => null, 'interwal_dni' => null, 'data_ostatniego_wykonania' => null, 'data_nastepnego_planowanego' => null, 'is_active' => 1]);
    $this->servicePlanRepository->shouldReceive('updatePlan')
        ->andReturn(['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'interwal_km' => 20000, 'interwal_dni' => null, 'data_ostatniego_wykonania' => null, 'data_nastepnego_planowanego' => null, 'is_active' => 1]);

    $request = new Request(query: [], body: ['typ_przegladu' => 'olejowy', 'interwal_km' => 20000], headers: []);
    $response = $this->equipmentController->updateServicePlan($request, ['id' => '3']);

    expect($response->statusCode())->toBe(200);
    expect($response->data()['interwal_km'])->toBe(20000);
});

it('deleteServicePlan returns 404 for missing plan', function (): void {
    $this->servicePlanRepository->shouldReceive('findById')->with(9)->andReturnNull();

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->deleteServicePlan($request, ['id' => '9']);
    expect($response->statusCode())->toBe(404);
});

it('deleteServicePlan removes plan and returns success', function (): void {
    $this->servicePlanRepository->shouldReceive('findById')->with(3)->andReturn(['id' => 3, 'equipment_id' => 1, 'typ_przegladu' => 'olejowy', 'is_active' => 1]);
    $this->servicePlanRepository->shouldReceive('deletePlan')->with(3)->andReturn(true);

    $request = new Request(query: [], body: [], headers: []);
    $response = $this->equipmentController->deleteServicePlan($request, ['id' => '3']);
    expect($response->statusCode())->toBe(200);
    expect($response->data()['success'])->toBeTrue();
});