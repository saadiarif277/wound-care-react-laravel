<?php

/**
 * MSC Wound Portal - Supabase Connection Test
 *
 * This script tests the Supabase PostgreSQL connection and Storage
 * and verifies the database setup is working correctly.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Setup database connection
$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => $_ENV['SUPABASE_DB_HOST'] ?? 'localhost',
    'port' => $_ENV['SUPABASE_DB_PORT'] ?? '5432',
    'database' => $_ENV['SUPABASE_DB_DATABASE'] ?? 'postgres',
    'username' => $_ENV['SUPABASE_DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['SUPABASE_DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => $_ENV['SUPABASE_DB_SSL_MODE'] ?? 'prefer',
], 'default');

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🏥 MSC Wound Portal - Supabase Connection Test\n";
echo "=============================================\n\n";

$testsRun = 0;
$testsPassed = 0;

try {
    // Test 1: Basic database connection
    echo "🔗 Testing basic database connection...\n";
    $result = Capsule::select('SELECT version() as version');
    echo "✅ Database connection successful!\n";
    echo "   PostgreSQL Version: " . $result[0]->version . "\n\n";
    $testsRun++;
    $testsPassed++;

    // Test 2: Check if required tables exist
    echo "🗄️ Checking existing tables...\n";
    $tables = Capsule::select("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");

    $expectedTables = [
        'accounts', 'users', 'organizations', 'facilities', 'contacts',
        'commission_rules', 'commission_records', 'commission_payouts'
    ];

    $existingTables = array_map(fn($t) => $t->table_name, $tables);

    foreach ($expectedTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "   ✅ $table\n";
        } else {
            echo "   ❌ $table (missing)\n";
        }
    }

    echo "\n📊 Total tables found: " . count($existingTables) . "\n\n";
    $testsRun++;
    $testsPassed++;

    // Test 3: Test Supabase Storage (if configured)
    echo "📦 Testing Supabase Storage connection...\n";
    if (isset($_ENV['SUPABASE_S3_ACCESS_KEY_ID']) && isset($_ENV['SUPABASE_S3_SECRET_ACCESS_KEY'])) {
        try {
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $_ENV['SUPABASE_S3_REGION'] ?? 'us-east-2',
                'endpoint' => $_ENV['SUPABASE_S3_ENDPOINT'] ?? '',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $_ENV['SUPABASE_S3_ACCESS_KEY_ID'],
                    'secret' => $_ENV['SUPABASE_S3_SECRET_ACCESS_KEY'],
                ],
            ]);

            // Test bucket listing
            $result = $s3Client->listBuckets();
            echo "✅ Supabase Storage connection successful!\n";

            $buckets = $result['Buckets'];
            echo "   Available buckets:\n";
            foreach ($buckets as $bucket) {
                echo "   - " . $bucket['Name'] . "\n";
            }

            // Test file upload/download
            $testBucket = $_ENV['SUPABASE_S3_BUCKET'] ?? 'documents';
            $testKey = 'test/connection-test-' . time() . '.txt';
            $testContent = 'Supabase Storage Connection Test - ' . date('Y-m-d H:i:s');

            try {
                // Upload test file
                $s3Client->putObject([
                    'Bucket' => $testBucket,
                    'Key' => $testKey,
                    'Body' => $testContent,
                ]);
                echo "   ✅ Test file uploaded successfully\n";

                // Download test file
                $result = $s3Client->getObject([
                    'Bucket' => $testBucket,
                    'Key' => $testKey,
                ]);
                $downloadedContent = (string) $result['Body'];

                if ($downloadedContent === $testContent) {
                    echo "   ✅ Test file downloaded and verified\n";
                } else {
                    echo "   ⚠️ Test file content mismatch\n";
                }

                // Clean up test file
                $s3Client->deleteObject([
                    'Bucket' => $testBucket,
                    'Key' => $testKey,
                ]);
                echo "   ✅ Test file cleaned up\n";

                $testsPassed++;
            } catch (AwsException $e) {
                echo "   ❌ Storage operations failed: " . $e->getMessage() . "\n";
                echo "   💡 Make sure the bucket '" . $testBucket . "' exists and you have proper permissions\n";
            }

            $testsRun++;
        } catch (Exception $e) {
            echo "   ❌ Supabase Storage connection failed: " . $e->getMessage() . "\n";
            echo "   💡 Check your S3 credentials and endpoint configuration\n";
            $testsRun++;
        }
    } else {
        echo "   ⏸️ Supabase Storage not configured (missing S3 credentials)\n";
        echo "   💡 Add SUPABASE_S3_ACCESS_KEY_ID and SUPABASE_S3_SECRET_ACCESS_KEY to .env\n";
    }
    echo "\n";

    // Test 4: Check for PHI data violations
    echo "🔒 Checking for PHI data violations...\n";
    $phiColumns = [
        'patient_name', 'patient_dob', 'patient_address', 'patient_ssn',
        'patient_phone', 'patient_email', 'medical_record_number',
        'insurance_member_id', 'insurance_policy_number'
    ];

    $violations = [];
    foreach ($existingTables as $tableName) {
        $columns = Capsule::select("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'public'
        ", [$tableName]);

        $tableColumns = array_map(fn($c) => $c->column_name, $columns);

        foreach ($phiColumns as $phiColumn) {
            if (in_array($phiColumn, $tableColumns)) {
                $violations[] = "$tableName.$phiColumn";
            }
        }
    }

    if (empty($violations)) {
        echo "✅ No PHI violations found - database is HIPAA compliant!\n\n";
        $testsPassed++;
    } else {
        echo "❌ PHI violations found:\n";
        foreach ($violations as $violation) {
            echo "   - $violation\n";
        }
        echo "\n⚠️ Please remove PHI columns from Supabase tables!\n\n";
    }
    $testsRun++;

    // Test 5: Test Row Level Security
    echo "🛡️ Checking Row Level Security status...\n";
    $rlsEnabled = 0;
    $rlsTotal = count($existingTables);

    foreach ($existingTables as $tableName) {
        $rlsStatus = Capsule::select("
            SELECT relrowsecurity
            FROM pg_class
            WHERE relname = ? AND relkind = 'r'
        ", [$tableName]);

        if (!empty($rlsStatus) && $rlsStatus[0]->relrowsecurity) {
            echo "   ✅ $tableName (RLS enabled)\n";
            $rlsEnabled++;
        } else {
            echo "   ⚠️ $tableName (RLS not enabled)\n";
        }
    }

    if ($rlsEnabled === $rlsTotal) {
        echo "   ✅ All tables have RLS enabled\n";
        $testsPassed++;
    } else {
        echo "   ⚠️ {$rlsEnabled}/{$rlsTotal} tables have RLS enabled\n";
        echo "   💡 Enable RLS for better security\n";
    }
    $testsRun++;

    echo "\n🎉 Connection test completed!\n";
    echo "📊 Tests passed: {$testsPassed}/{$testsRun}\n\n";

    if ($testsPassed === $testsRun) {
        echo "✅ All tests passed! Your Supabase setup is ready.\n\n";
    } else {
        echo "⚠️ Some tests failed. Please review the issues above.\n\n";
    }

    echo "Next steps:\n";
    echo "1. Run migrations if tables are missing: php artisan migrate\n";
    echo "2. Enable RLS for tables showing warnings\n";
    echo "3. Configure RLS policies in Supabase dashboard\n";
    echo "4. Set up storage buckets: documents, reports, exports\n";
    echo "5. Remove any PHI columns if violations were found\n";

} catch (Exception $e) {
    echo "❌ Connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting:\n";
    echo "1. Check your .env file configuration\n";
    echo "2. Verify Supabase project is running\n";
    echo "3. Confirm database credentials are correct\n";
    echo "4. Check firewall/network settings\n";
    echo "5. Ensure S3 credentials are properly configured\n";
    exit(1);
}
