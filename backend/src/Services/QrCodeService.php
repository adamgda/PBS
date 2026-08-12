<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Generator kodów QR dla maszyn (Etap 20).
 *
 * Wrapper wokół sprawdzonej, zgodnej ze specyfikacją ISO/IEC 18004 biblioteki
 * `chillerlan/php-qrcode` — zwraca kod jako SVG (wektorowy, odpowiedni do
 * wydruku naklejki i skanowania kamerą telefonu).
 *
 * Poziom korekcji M (15%) — dobry kompromis dla naklejek/druku.
 */
final class QrCodeService
{
    /**
     * Generuje kod QR jako SVG.
     */
    public function svg(string $data, int $pixelSize = 8, int $quietZone = 4): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'scale' => $pixelSize,
            'outputInterface' => QRMarkupSVG::class,
            'quietzoneSize' => $quietZone,
            'svgAddXmlHeader' => false,
            'svgDefs' => '',
            'drawCircularModules' => false,
            'connectPaths' => false,
            'outputBase64' => false,
        ]);

        $qrcode = new QRCode($options);

        $svg = $qrcode->render($data);
        if (!is_string($svg)) {
            throw new \RuntimeException('QR render did not return a string');
        }

        return $svg;
    }
}
