<?php
/**
 * @file classes/migration/install/RorsMigration.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RorsMigration
 *
 * @brief Describe database table structures.
 *
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class RorsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rors', function (Blueprint $table) {
            $table->comment('Ror registry dataset cache');
            $table->bigInteger('ror_id')->autoIncrement();
            $table->string('ror')->nullable(false);

            $table->unique(['ror'], 'rors_unique');
        });

        Schema::create('ror_settings', function (Blueprint $table) {
            $table->comment('More data about Ror, including localized properties like names.');
            $table->bigInteger('ror_setting_id')->autoIncrement();
            $table->bigInteger('ror_id');
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->index(['ror_id'], 'ror_settings_ror_id');
            $table->unique(['ror_id', 'locale', 'setting_name'], 'ror_settings_unique');
            $table->foreign('ror_id')->references('ror_id')->on('rors')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('ror_settings');
        Schema::drop('rors');
    }
}
