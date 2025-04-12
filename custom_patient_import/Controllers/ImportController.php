<?php
/**
 * Import Controller for Patient Import Module
 * @package OpenEMR 7.0.2
 * @author  Javier Ortiz Torres
 * @link    https://pido.ar
 */

namespace OpenEMR\Modules\CustomPatientImport\Controllers;

use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\AddressService;
use OpenEMR\Services\PhoneNumberService;
use OpenEMR\Services\EmailService;
use OpenEMR\Services\PatientDemographicsService;

class ImportController
{
    private $patientService;
    private $addressService;
    private $phoneNumberService;
    private $emailService;
    private $demographicsService;
    private $logger;

    public function __construct()
    {
        $this->logger = new SystemLogger();
        $this->patientService = new PatientService();
        $this->addressService = new AddressService();
        $this->phoneNumberService = new PhoneNumberService();
        $this->emailService = new EmailService();
        $this->demographicsService = new PatientDemographicsService();
    }

    /**
     * Process CSV file and import patients
     * @param string $filePath    Path to CSV file
     * @param string $delimiter   CSV delimiter
     * @param bool   $hasHeaders  Whether CSV has header row
     * @return array              Results array
     */
    public function importPatientsFromCSV($filePath, $delimiter = ',', $hasHeaders = true)
    {
        $results = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];

        if (!file_exists($filePath)) {
            $this->logger->error("CSV file not found", ['file' => $filePath]);
            throw new \Exception(xlt("CSV file not found"));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->logger->error("Could not open CSV file", ['file' => $filePath]);
            throw new \Exception(xlt("Could not open CSV file"));
        }

        $rowNumber = 0;
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $rowNumber++;
            
            // Skip header row if present
            if ($rowNumber === 1 && $hasHeaders) {
                continue;
            }

            try {
                $patientData = $this->mapCSVToPatient($row);
                $validation = $this->validatePatientData($patientData);
                
                if (!$validation['is_valid']) {
                    throw new \Exception($validation['message']);
                }

                $pid = $this->savePatient($patientData);
                
                if ($pid) {
                    $results['success_count']++;
                } else {
                    throw new \Exception(xlt("Failed to save patient"));
                }
            } catch (\Exception $e) {
                $results['error_count']++;
                $results['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                    'data' => $row
                ];
                $this->logger->error("Patient import error", [
                    'row' => $rowNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }

        fclose($handle);
        return $results;
    }

    /**
     * Map CSV row to patient data structure
     * @param array $row CSV row data
     * @return array     Mapped patient data
     */
    private function mapCSVToPatient($row)
    {
        return [
            'fname' => $row[0] ?? '',
            'lname' => $row[1] ?? '',
            'mname' => $row[2] ?? '',
            'dob' => $row[3] ?? '',
            'sex' => $row[4] ?? '',
            'ss' => $row[5] ?? '',
            'street' => $row[6] ?? '',
            'city' => $row[7] ?? '',
            'state' => $row[8] ?? '',
            'postal_code' => $row[9] ?? '',
            'country_code' => $row[10] ?? '',
            'phone_home' => $row[11] ?? '',
            'phone_cell' => $row[12] ?? '',
            'email' => $row[13] ?? '',
            'status' => $row[14] ?? 'active',
            'providerID' => $row[15] ?? 1,
            'race' => $row[16] ?? '',
            'ethnicity' => $row[17] ?? ''
        ];
    }

    /**
     * Validate patient data before import
     * @param array $patientData Patient data
     * @return array Validation result
     */
    private function validatePatientData($patientData)
    {
        if (empty($patientData['fname'])) {
            return ['is_valid' => false, 'message' => xlt('First name is required')];
        }
        
        if (empty($patientData['lname'])) {
            return ['is_valid' => false, 'message' => xlt('Last name is required')];
        }
        
        if (!$this->validateDate($patientData['dob'])) {
            return ['is_valid' => false, 'message' => xlt('Invalid birth date')];
        }
        
        if (!in_array($patientData['sex'], ['M', 'F', 'U'])) {
            return ['is_valid' => false, 'message' => xlt('Invalid sex (must be M, F or U)')];
        }
        
        return ['is_valid' => true, 'message' => ''];
    }

    /**
     * Save patient data to database
     * @param array $patientData Patient data
     * @return int|bool          Patient ID or false on failure
     */
    private function savePatient($patientData)
    {
        // Generate unique pubpid
        $patientData['pubpid'] = $this->generateNewPubpid();
        
        // Save patient data
        $pid = $this->patientService->insert($patientData);
        
        if ($pid) {
            // Save address
            $addressData = [
                'foreign_id' => $pid,
                'line1' => $patientData['street'],
                'city' => $patientData['city'],
                'state' => $patientData['state'],
                'postal_code' => $patientData['postal_code'],
                'country_code' => $patientData['country_code']
            ];
            $this->addressService->insert($addressData);
            
            // Save phone numbers
            if (!empty($patientData['phone_home'])) {
                $this->phoneNumberService->insert([
                    'foreign_id' => $pid,
                    'type' => 'home',
                    'number' => $patientData['phone_home']
                ]);
            }
            
            if (!empty($patientData['phone_cell'])) {
                $this->phoneNumberService->insert([
                    'foreign_id' => $pid,
                    'type' => 'cell',
                    'number' => $patientData['phone_cell']
                ]);
            }
            
            // Save email
            if (!empty($patientData['email'])) {
                $this->emailService->insert([
                    'foreign_id' => $pid,
                    'type' => 'primary',
                    'email' => $patientData['email']
                ]);
            }
            
            // Save demographics
            $this->demographicsService->insert([
                'pid' => $pid,
                'race' => $patientData['race'],
                'ethnicity' => $patientData['ethnicity'],
                'providerID' => $patientData['providerID']
            ]);
        }
        
        return $pid;
    }

    /**
     * Generate a new unique pubpid
     * @return string Generated pubpid
     */
    private function generateNewPubpid()
    {
        $prefix = 'IMP' . date('Ymd');
        $lastPubpid = $this->patientService->getLastPubpidLike($prefix . '%');
        
        if ($lastPubpid) {
            $lastNum = (int)substr($lastPubpid, strlen($prefix));
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }
        
        return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate date format (YYYY-MM-DD)
     * @param string $date Date string
     * @return bool        Whether date is valid
     */
    private function validateDate($date)
    {
        if (empty($date)) return false;
        
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}