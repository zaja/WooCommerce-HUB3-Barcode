<?php
/**
 * PDF417 Barcode Generator
 * Generates PDF417 2D barcode for HUB3 standard using tc-lib-barcode
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
$autoloader = WOO_HUB3_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

class WooPDF417Generator {
    
    private $width = 3;      // Width multiplier
    private $height = 3;     // Height multiplier
    private $color = array(0, 0, 0); // Black color
    
    public function __construct() {
        // Constructor
    }
    
    public function generate($data) {
        try {
            // Use tc-lib-barcode library
            $barcode = new \Com\Tecnick\Barcode\Barcode();
            
            // Generate PDF417 barcode
            $bobj = $barcode->getBarcodeObj(
                'PDF417',           // Barcode type
                $data,              // Data to encode
                -4,                 // Width (negative = multiplier)
                -4,                 // Height (negative = multiplier)
                'black',            // Foreground color
                array(-2, -2, -2, -2) // Padding
            );
            
            // Get SVG representation
            $svg = $bobj->getSvgCode();
            
            // Return as data URI
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
            
        } catch (Exception $e) {
            // Log error
            error_log('WooHUB3 Barcode Error: ' . $e->getMessage());
            
            // Return placeholder
            return $this->generate_error_placeholder();
        }
    }
    
    public function generate_png($data) {
        try {
            $barcode = new \Com\Tecnick\Barcode\Barcode();
            
            $bobj = $barcode->getBarcodeObj(
                'PDF417',
                $data,
                -3,
                -3,
                'black',
                array(-2, -2, -2, -2)
            );
            
            // Get PNG representation
            $png = $bobj->getPngData();
            
            return 'data:image/png;base64,' . base64_encode($png);
            
        } catch (Exception $e) {
            error_log('WooHUB3 Barcode PNG Error: ' . $e->getMessage());
            return $this->generate_error_placeholder();
        }
    }
    
    private function generate_error_placeholder() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100" viewBox="0 0 300 100">';
        $svg .= '<rect width="100%" height="100%" fill="#f5f5f5"/>';
        $svg .= '<text x="150" y="50" text-anchor="middle" fill="#999" font-family="sans-serif" font-size="14">';
        $svg .= 'Barcode generation error';
        $svg .= '</text>';
        $svg .= '</svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
