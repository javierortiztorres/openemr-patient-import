<?php
/**
 * Patient Import Interface
 * @package OpenEMR
 * @author Your Name
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/headers.inc.php");
require_once("$srcdir/acl.inc");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/formdata.inc.php");
require_once("$srcdir/formatting.inc.php");
require_once("../../library/csv_import_functions.php");
require_once("../../Controllers/ImportController.php");

use OpenEMR\Modules\CustomPatientImport\Controllers\ImportController;

if (!acl_check('admin', 'super')) {
    echo xlt("Access denied");
    exit;
}

// Procesar el formulario de importación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $delimiter = $_POST['delimiter'] ?? ',';
    $has_header = isset($_POST['header_row']);
    
    try {
        $importController = new ImportController();
        $results = $importController->importPatientsFromCSV(
            $_FILES['csv_file']['tmp_name'],
            $delimiter,
            $has_header
        );
        
        // Mostrar resultados
        displayResults($results);
        exit;
    } catch (Exception $e) {
        die(text($e->getMessage()));
    }
}

// Descargar archivo de ejemplo
if (isset($_GET['download_example'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="example_patients.csv"');
    
    $columns = [
        xl('fname'), xl('lname'), xl('mname'), xl('dob'), xl('sex'), 
        xl('ss'), xl('street'), xl('city'), xl('state'), xl('postal_code'), 
        xl('country_code'), xl('phone_home'), xl('phone_cell'), xl('email'), 
        xl('status'), xl('providerID'), xl('race'), xl('ethnicity')
    ];
    
    $example_data = [
        [
            xl('Juan'), xl('Pérez'), xl('A'), '1980-05-15', 'M', '123456789', 
            xl('Calle Falsa 123'), xl('Ciudad'), xl('Estado'), '12345', 'MX', 
            '5551234567', '5557654321', 'juan@ejemplo.com', 'active', '1', 
            xl('white'), xl('not_hispanic')
        ],
        [
            xl('María'), xl('Gómez'), xl('B'), '1975-11-22', 'F', '987654321', 
            xl('Avenida Real 456'), xl('Ciudad'), xl('Estado'), '54321', 'MX', 
            '5559876543', '5551234567', 'maria@ejemplo.com', 'active', '1', 
            xl('native'), xl('hispanic')
        ]
    ];
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $columns);
    foreach ($example_data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

/**
 * Display import results
 * @param array $results Import results
 */
function displayResults($results) {
    ?>
    <html>
    <head>
        <title><?php echo text(xlt('Import Results')); ?></title>
        <?php Header::setupHeader(); ?>
    </head>
    <body>
        <div class="container mt-3">
            <h2><?php echo text(xlt('Import Results')); ?></h2>
            
            <div class="alert alert-<?php echo ($results['error_count'] > 0) ? 'warning' : 'success'; ?>">
                <?php 
                echo text(xlt('Patients imported successfully') . ": " . $results['success_count'] . "<br>");
                echo text(xlt('Errors') . ": " . $results['error_count']);
                ?>
            </div>
            
            <?php if (!empty($results['errors'])) { ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <?php echo text(xlt('Detailed Errors')); ?>
                    </div>
                    <div class="card-body">
                        <ul>
                            <?php foreach ($results['errors'] as $error) { ?>
                                <li>
                                    <strong><?php echo text(xlt('Row') . ": " . $error['row']); ?></strong>:
                                    <?php echo text($error['message']); ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>
            
            <a href="patient_import.php" class="btn btn-primary">
                <i class="fa fa-arrow-left"></i> <?php echo text(xlt('Back')); ?>
            </a>
        </div>
    </body>
    </html>
    <?php
}
?>
<html>
<head>
    <title><?php echo xlt('Import Patients'); ?></title>
    <?php Header::setupHeader('datetime-picker'); ?>
    <script>
    $(document).ready(function() {
        $('#csv_file').change(function() {
            var file = this.files[0];
            if (!file) return;
            
            if (!file.name.endsWith('.csv')) {
                alert(<?php echo xjs('File must have .csv extension'); ?>);
                $(this).val('');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert(<?php echo xjs('File cannot be larger than 5MB'); ?>);
                $(this).val('');
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                var contents = e.target.result;
                var lines = contents.split('\n');
                var delimiter = $('#delimiter').val();
                
                if (lines.length < 2) {
                    alert(<?php echo xjs('File does not contain enough rows'); ?>);
                    $('#csv_file').val('');
                    return;
                }
                
                var firstRow = lines[0].split(delimiter);
                if (firstRow.length < 5) {
                    alert(<?php echo xjs('File does not contain enough columns'); ?>);
                    $('#csv_file').val('');
                    return;
                }
                
                $('#file-preview').html(
                    '<h5><?php echo xla("Preview"); ?></h5>' +
                    '<pre>' + lines.slice(0, 5).join('\n') + '</pre>'
                );
            };
            reader.readAsText(file);
        });
        
        $('form').submit(function(e) {
            if (!$('#csv_file').val()) {
                alert(<?php echo xjs('You must select a CSV file'); ?>);
                e.preventDefault();
                return false;
            }
            
            $('#submit-btn').html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' +
                <?php echo xjs('Processing...'); ?>
            ).prop('disabled', true);
        });
    });
    </script>
</head>
<body>
    <div class="container mt-3">
        <h2><?php echo xlt('Bulk Patient Import'); ?></h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <?php echo xlt('Instructions'); ?>
            </div>
            <div class="card-body">
                <ol>
                    <li><?php echo xlt('Download the example file to see the required format'); ?></li>
                    <li><?php echo xlt('Prepare your CSV file with patient data'); ?></li>
                    <li><?php echo xlt('Select the file and click Import'); ?></li>
                </ol>
                
                <h5><?php echo xlt('CSV File Format'); ?></h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th><?php echo xlt('Column'); ?></th>
                            <th><?php echo xlt('Description'); ?></th>
                            <th><?php echo xlt('Required'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>fname</td>
                            <td><?php echo xlt('Patient first name'); ?></td>
                            <td><?php echo xlt('Yes'); ?></td>
                        </tr>
                        <tr>
                            <td>lname</td>
                            <td><?php echo xlt('Patient last name'); ?></td>
                            <td><?php echo xlt('Yes'); ?></td>
                        </tr>
                        <!-- Otras columnas... -->
                    </tbody>
                </table>
                
                <a href="?download_example=1" class="btn btn-secondary">
                    <i class="fa fa-download"></i> <?php echo xlt('Download Example'); ?>
                </a>
            </div>
        </div>
        
        <form method="post" action="patient_import.php" enctype="multipart/form-data" class="border p-3">
            <div class="form-group">
                <label for="csv_file"><?php echo xlt('CSV File'); ?></label>
                <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                <small class="form-text text-muted">
                    <?php echo xlt('Maximum file size: 5MB'); ?>
                </small>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="header_row" name="header_row" checked>
                <label class="form-check-label" for="header_row">
                    <?php echo xlt('File contains header row'); ?>
                </label>
            </div>
            
            <div class="form-group">
                <label for="delimiter"><?php echo xlt('Delimiter'); ?></label>
                <select class="form-control" id="delimiter" name="delimiter">
                    <option value=","><?php echo xlt('Comma'); ?></option>
                    <option value=";"><?php echo xlt('Semicolon'); ?></option>
                    <option value="\t"><?php echo xlt('Tab'); ?></option>
                </select>
            </div>
            
            <div id="file-preview" class="mt-3"></div>
            
            <button type="submit" id="submit-btn" class="btn btn-primary">
                <i class="fa fa-file-import"></i> <?php echo xlt('Import Patients'); ?>
            </button>
        </form>
    </div>
</body>
</html>