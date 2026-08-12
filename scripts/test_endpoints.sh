#!/usr/bin/env bash
# Test wszystkich endpointów PBS API
set -uo pipefail
BASE="http://localhost:8080"
EMAIL="adammz.gda@gmail.com"
PASS="TestoweHaslo2026!"
GREEN="\033[0;32m"; RED="\033[0;31m"; NC="\033[0m"
pass=0; fail=0

req() {
  local method="$1" path="$2" body="${3:-}" extra="${4:-}"
  local out code
  if [ -n "$body" ]; then
    out=$(curl -s -w "\n%{http_code}" -X "$method" "$BASE$path" \
      -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
      -d "$body" $extra)
  else
    out=$(curl -s -w "\n%{http_code}" -X "$method" "$BASE$path" \
      -H "Authorization: Bearer $TOKEN" $extra)
  fi
  code=$(echo "$out" | tail -1)
  echo "$out" | sed '$d'
  echo "$code"
}
body() {
  local method="$1" path="$2" body="${3:-}"
  if [ -n "$body" ]; then
    curl -s -X "$method" "$BASE$path" -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" -d "$body"
  else
    curl -s -X "$method" "$BASE$path" -H "Authorization: Bearer $TOKEN"
  fi
}
check() {
  local name="$1" code="$2"
  if [ "$code" = "200" ] || [ "$code" = "201" ]; then
    printf "  ${GREEN}OK${NC}  %-45s [%s]\n" "$name" "$code"; pass=$((pass+1))
  else
    printf "  ${RED}FAIL${NC} %-45s [%s]\n" "$name" "$code"; fail=$((fail+1))
  fi
}

echo "=== 1. Logowanie ==="
LOGIN=$(curl -s -X POST "$BASE/api/v1/auth/login" -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}")
echo "$LOGIN"
TOKEN=$(echo "$LOGIN" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["access_token"]??"";')
REFRESH=$(echo "$LOGIN" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["refresh_token"]??"";')
if [ -z "$TOKEN" ]; then echo "Brak tokena — przerywam."; exit 1; fi
echo "Token OK (długość: ${#TOKEN})"

echo ""
echo "=== 2. Endpointy (GET) ==="
for ep in \
  "/api/v1/health" "/api/v1/users" "/api/v1/terminals" "/api/v1/employees" \
  "/api/v1/equipment" "/api/v1/orders" "/api/v1/incidents" "/api/v1/invoices" \
  "/api/v1/invoices/missing" "/api/v1/reports/terminal" "/api/v1/reports/vehicle" \
  "/api/v1/analytics/overview" "/api/v1/analytics/terminals" "/api/v1/analytics/employees" \
  "/api/v1/analytics/equipment" "/api/v1/analytics/relations" \
  "/api/v1/dashboard/summary" "/api/v1/dashboard/alerts" "/api/v1/dashboard/charts" \
  "/api/v1/audit-logs" "/api/v1/settings/alert-configs" "/api/v1/notes" ; do
  out=$(req GET "$ep"); code=$(echo "$out" | tail -1); check "$ep" "$code"
done

echo ""
echo "=== 3. Endpointy (GET pojedyncze) ==="
TERM_ID=$(body GET "/api/v1/terminals" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
EMP_ID=$(body GET "/api/v1/employees" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
EQ_ID=$(body GET "/api/v1/equipment" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
ORD_ID=$(body GET "/api/v1/orders" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
INC_ID=$(body GET "/api/v1/incidents" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
INV_ID=$(body GET "/api/v1/invoices" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["id"]??"";' 2>/dev/null)
echo "  ID: term=$TERM_ID emp=$EMP_ID eq=$EQ_ID ord=$ORD_ID inc=$INC_ID inv=$INV_ID"

out=$(req GET "/api/v1/terminals/$TERM_ID"); check "/api/v1/terminals/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/employees/$EMP_ID"); check "/api/v1/employees/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/equipment/$EQ_ID"); check "/api/v1/equipment/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/equipment/$EQ_ID/timeline"); check "/api/v1/equipment/{id}/timeline" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/equipment/$EQ_ID/service-plans"); check "/api/v1/equipment/{id}/service-plans" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/orders/$ORD_ID"); check "/api/v1/orders/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/incidents/$INC_ID"); check "/api/v1/incidents/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/invoices/$INV_ID"); check "/api/v1/invoices/{id}" "$(echo "$out" | tail -1)"
out=$(req GET "/api/v1/reports/terminal/$TERM_ID"); check "/api/v1/reports/terminal/{id}" "$(echo "$out" | tail -1)"

echo ""
echo "=== 4. Endpointy mutujące ==="
out=$(req POST "/api/v1/terminals" '{"nazwa":"Terminal Testowy","adres":"ul. Testowa 1","operator":"Test Op","telefon_operatora":"123456789","email_operatora":"test@test.pl"}')
code=$(echo "$out" | tail -1); check "POST /terminals" "$code"
TEST_TERM=$(echo "$out" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["id"]??($d["id"]??"");' 2>/dev/null)
if [ -n "$TEST_TERM" ]; then
  out=$(req DELETE "/api/v1/terminals/$TEST_TERM"); check "DELETE /terminals/{id}" "$(echo "$out" | tail -1)"
fi

out=$(req POST "/api/v1/notes" '{"tresc":"Testowa notatka","is_done":false}')
code=$(echo "$out" | tail -1); check "POST /notes" "$code"
NT=$(echo "$out" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["id"]??($d["id"]??"");' 2>/dev/null)
if [ -n "$NT" ]; then
  out=$(req PATCH "/api/v1/notes/$NT/done" '{}'); check "PATCH /notes/{id}/done" "$(echo "$out" | tail -1)"
  out=$(req DELETE "/api/v1/notes/$NT"); check "DELETE /notes/{id}" "$(echo "$out" | tail -1)"
fi

out=$(req PATCH "/api/v1/incidents/$INC_ID/status" '{"status":"w_trakcie_naprawy"}'); check "PATCH /incidents/{id}/status" "$(echo "$out" | tail -1)"
out=$(req POST "/api/v1/incidents/$INC_ID/comments" '{"tresc":"Testowy komentarz"}'); check "POST /incidents/{id}/comments" "$(echo "$out" | tail -1)"
out=$(req PATCH "/api/v1/invoices/$INV_ID/status" '{"status":"zaplacona"}'); check "PATCH /invoices/{id}/status" "$(echo "$out" | tail -1)"

out=$(req POST "/api/v1/orders/$ORD_ID/assign-employee" "{\"employee_id\":$EMP_ID,\"rola\":\"operator\",\"godziny\":8.0}")
code=$(echo "$out" | tail -1); check "POST /orders/{id}/assign-employee" "$code"
EE=$(echo "$out" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["employee_id"]??($d["employee_id"]??"");' 2>/dev/null)
if [ -n "$EE" ]; then
  out=$(req DELETE "/api/v1/orders/$ORD_ID/assign-employee/$EMP_ID"); check "DELETE /orders/{id}/assign-employee/{eid}" "$(echo "$out" | tail -1)"
fi
out=$(req POST "/api/v1/orders/$ORD_ID/assign-equipment" "{\"equipment_id\":$EQ_ID}")
code=$(echo "$out" | tail -1); check "POST /orders/{id}/assign-equipment" "$code"
QE=$(echo "$out" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["equipment_id"]??($d["equipment_id"]??"");' 2>/dev/null)
if [ -n "$QE" ]; then
  out=$(req DELETE "/api/v1/orders/$ORD_ID/assign-equipment/$EQ_ID"); check "DELETE /orders/{id}/assign-equipment/{eid}" "$(echo "$out" | tail -1)"
fi

out=$(req POST "/api/v1/reports/terminal" "{\"terminal_id\":$TERM_ID,\"data_raportu\":\"$(date +%F)\",\"opis\":\"Raport testowy\",\"uwagi\":null}")
code=$(echo "$out" | tail -1); check "POST /reports/terminal" "$code"
TR_ID=$(echo "$out" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["id"]??($d["id"]??"");' 2>/dev/null)
if [ -n "$TR_ID" ]; then
  out=$(req PUT "/api/v1/reports/terminal/$TR_ID" "{\"opis\":\"Raport zaktualizowany\",\"uwagi\":\"x\"}"); check "PUT /reports/terminal/{id}" "$(echo "$out" | tail -1)"
fi

echo ""
echo "=== 5. Auth (refresh, me, csrf) ==="
out=$(req POST "/api/v1/auth/refresh" "{\"refresh_token\":\"$REFRESH\"}")
check "POST /auth/refresh" "$(echo "$out" | tail -1)"
out=$(curl -s -w "\n%{http_code}" -X GET "$BASE/api/v1/auth/csrf" -H "Authorization: Bearer $TOKEN"); check "GET /auth/csrf" "$(echo "$out" | tail -1)"
out=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/v1/auth/logout" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "{\"refresh_token\":\"$REFRESH\"}"); check "POST /auth/logout" "$(echo "$out" | tail -1)"

echo ""
echo "======================================"
printf "WYNIK: %sOK=%d%s %sFAIL=%d%s\n" "$GREEN" "$pass" "$NC" "$RED" "$fail" "$NC"

