# Módulo de Importación Masiva de Pacientes para OpenEMR
Módulo para importar pacientes desde archivos CSV en OpenEMR 7.0.2

custom_patient_import/
├── Controllers/
│   └── ImportController.php
├── interface/
│   └── patient_import/
│       ├── patient_import.php
│       ├── process_import.php
│       └── template/
│           └── example_patients.csv
├── library/
│   └── csv_import_functions.php
├── sql/
│   └── sql_upgrade.php
├── lang/
│   ├── en_us/
│   │   └── custom_patient_import.php
│   └── es_ES/
│       └── custom_patient_import.php
└── custom_patient_import.php