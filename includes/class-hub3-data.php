<?php
/**
 * HUB3 Data Generator
 * Generates HUB3 standard compliant data string for PDF417 barcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooHUB3Data {
    
    private $options;
    private $order_data;
    
    // HUB3 field lengths according to specification
    private $field_lengths = array(
        'header' => 8,           // HRVHUB30
        'currency' => 3,         // EUR
        'amount' => 15,          // Amount in cents, zero-padded
        'payer_name' => 30,
        'payer_address' => 27,
        'payer_city' => 27,
        'recipient_name' => 25,
        'recipient_address' => 25,
        'recipient_city' => 27,
        'iban' => 21,
        'model' => 4,
        'reference' => 22,
        'purpose_code' => 4,
        'description' => 35,
    );
    
    public function __construct($options, $order_data) {
        $this->options = $options;
        $this->order_data = $order_data;
    }
    
    public function generate() {
        $lines = array();
        
        // Line 1: Header - always HRVHUB30
        $lines[] = 'HRVHUB30';
        
        // Line 2: Currency
        $currency = $this->order_data['currency'] ?? 'EUR';
        $lines[] = $currency;
        
        // Line 3: Amount (in cents, 15 digits, zero-padded)
        $amount = $this->format_amount($this->order_data['total']);
        $lines[] = $amount;
        
        // Line 4: Payer name
        $lines[] = $this->truncate_and_clean($this->order_data['payer_name'], $this->field_lengths['payer_name']);
        
        // Line 5: Payer address
        $lines[] = $this->truncate_and_clean($this->order_data['payer_address'], $this->field_lengths['payer_address']);
        
        // Line 6: Payer city (with postal code)
        $lines[] = $this->truncate_and_clean($this->order_data['payer_city'], $this->field_lengths['payer_city']);
        
        // Line 7: Recipient name
        $lines[] = $this->truncate_and_clean($this->options['recipient_name'], $this->field_lengths['recipient_name']);
        
        // Line 8: Recipient address
        $lines[] = $this->truncate_and_clean($this->options['recipient_address'], $this->field_lengths['recipient_address']);
        
        // Line 9: Recipient city (with postal code)
        $recipient_city = $this->options['recipient_postal'] . ' ' . $this->options['recipient_city'];
        $lines[] = $this->truncate_and_clean($recipient_city, $this->field_lengths['recipient_city']);
        
        // Line 10: IBAN
        $iban = $this->clean_iban($this->options['recipient_iban']);
        $lines[] = $iban;
        
        // Line 11: Payment model
        $lines[] = $this->options['payment_model'] ?? 'HR99';
        
        // Line 12: Reference number (poziv na broj)
        $reference = $this->generate_reference();
        $lines[] = $this->truncate_and_clean($reference, $this->field_lengths['reference']);
        
        // Line 13: Purpose code
        $purpose_code = $this->options['purpose_code'] ?? 'OTHR';
        if ($purpose_code === 'custom' && !empty($this->options['purpose_code_custom'])) {
            $purpose_code = strtoupper(substr($this->options['purpose_code_custom'], 0, 4));
        }
        $lines[] = $purpose_code;
        
        // Line 14: Payment description
        $description = $this->parse_variables($this->options['payment_description'] ?? 'Plaćanje narudžbe #{order_number}');
        $lines[] = $this->truncate_and_clean($description, $this->field_lengths['description']);
        
        // Join with newline character (LF = chr(10))
        return implode("\n", $lines);
    }
    
    private function format_amount($amount) {
        // Convert to cents and pad to 15 digits
        $cents = round(floatval($amount) * 100);
        return str_pad($cents, 15, '0', STR_PAD_LEFT);
    }
    
    private function truncate_and_clean($string, $max_length) {
        // Convert Croatian characters to ASCII equivalents for barcode compatibility
        $string = $this->convert_croatian_chars($string);
        
        // Remove any newlines or special characters
        $string = preg_replace('/[\r\n\t]/', ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);
        
        // Truncate to max length
        if (mb_strlen($string) > $max_length) {
            $string = mb_substr($string, 0, $max_length);
        }
        
        return $string;
    }
    
    private function convert_croatian_chars($string) {
        // HUB3 standard allows Croatian characters, but some readers might have issues
        // Keep them as-is since modern readers support UTF-8
        return $string;
    }
    
    private function clean_iban($iban) {
        // Remove spaces and convert to uppercase
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }
    
    private function generate_reference() {
        $format = $this->options['reference_format'] ?? 'order_number';
        $date_format = $this->options['date_format'] ?? 'dmY';
        $prefix = $this->options['reference_prefix'] ?? '';
        $suffix = $this->options['reference_suffix'] ?? '';
        
        $order_number = $this->order_data['order_number'];
        $order_date = strtotime($this->order_data['order_date']);
        $formatted_date = date($date_format, $order_date);
        
        $reference = '';
        
        switch ($format) {
            case 'order_number':
                $reference = $order_number;
                break;
            case 'order_date':
                $reference = $order_number . '-' . $formatted_date;
                break;
            case 'date_order':
                $reference = $formatted_date . '-' . $order_number;
                break;
            case 'only_date':
                $reference = $formatted_date;
                break;
            default:
                $reference = $order_number;
        }
        
        // Add prefix and suffix
        if (!empty($prefix)) {
            $reference = $prefix . $reference;
        }
        if (!empty($suffix)) {
            $reference = $reference . $suffix;
        }
        
        // Clean reference - only numbers and dashes allowed
        $reference = preg_replace('/[^0-9\-]/', '', $reference);
        
        return $reference;
    }
    
    private function parse_variables($string) {
        $replacements = array(
            '{order_number}' => $this->order_data['order_number'],
            '{order_date}' => $this->order_data['order_date'],
            '#{order_number}' => '#' . $this->order_data['order_number'],
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $string);
    }
}
