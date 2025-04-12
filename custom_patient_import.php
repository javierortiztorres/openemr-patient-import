<?php
/**
 * Custom Patient Import Module for OpenEMR
 * @package OpenEMR 7.0.2
 * @author Javier Ortiz Torres
 * @copyright Copyright (c) Pido.ar
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__).'/../../globals.php');
require_once($GLOBALS["srcdir"]."/api.inc");

function custom_patient_import_config() {
    return array(
        'name' => 'Custom Patient Import',
        'version' => '1.0',
        'author' => 'Your Name',
        'description' => xl('Allows bulk import of patients from CSV files'),
        'icon' => 'fa fa-users',
        'compatible_version' => '7.0.2',
        'is_enabled' => '1',
        'required_modules' => array()
    );
}

function custom_patient_import_menu() {
    return array(
        'label' => xl('Import Patients'),
        'url' => '/interface/patient_import/patient_import.php',
        'icon' => 'fa fa-file-import',
        'requirement' => 'admin',
        'children' => array()
    );
}

// Register the module
$module_config = custom_patient_import_config();
$_SESSION['oemr_user_mods']['custom_patient_import'] = $module_config;
$_SESSION['oemr_user_mods']['custom_patient_import']['menu'] = custom_patient_import_menu();