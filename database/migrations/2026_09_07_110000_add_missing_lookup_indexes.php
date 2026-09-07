<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign-key and filter columns that every hasMany lookup, the admin
     * overview filters and the product guards scan. MySQL creates an index
     * for every foreignId()->constrained() automatically, SQLite does not,
     * so on SQLite these were all full-table scans. Every index is only
     * created when no index over exactly those columns exists yet, which
     * keeps the migration safe on both engines.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    private const INDEXES = [
        'customer_requests' => [['status'], ['created_at'], ['service_category'], ['customer_email']],
        'customer_request_attachments' => [['customer_request_id']],
        'customer_request_notes' => [['customer_request_id']],
        'customer_request_appointments' => [['customer_request_id']],
        'mail_logs' => [['customer_request_id']],
        'quotes' => [['quote_status']],
        'hvac_products' => [['hvac_brand_id']],
        'hvac_product_compatibilities' => [['compatible_product_id']],
        'hvac_calculations' => [['customer_request_id'], ['hvac_rule_set_id']],
        'hvac_recommendations' => [['hvac_calculation_id']],
        'hvac_recommendation_items' => [['hvac_recommendation_id'], ['hvac_product_id']],
        'hvac_manual_overrides' => [['hvac_calculation_id']],
        'hvac_import_catalog_product' => [['hvac_product_id']],
        'hvac_ai_logs' => [['hvac_recommendation_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_map(
                fn (array $index) => array_map('strtolower', $index['columns']),
                Schema::getIndexes($table)
            );

            foreach ($indexes as $columns) {
                if (! Schema::hasColumns($table, $columns)) {
                    continue;
                }

                if (in_array(array_map('strtolower', $columns), $existing, true)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                    $blueprint->index($columns);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existingNames = array_map(fn (array $index) => $index['name'], Schema::getIndexes($table));

            foreach ($indexes as $columns) {
                $name = $table . '_' . implode('_', $columns) . '_index';

                if (in_array($name, $existingNames, true)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                        $blueprint->dropIndex($name);
                    });
                }
            }
        }
    }
};
