<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGatewayTransactionsTable extends Migration
{

    private function getTable()
    {
        return config(\Parsisolution\Gateway\GatewayManager::CONFIG_FILE_NAME.'.table', 'gateway_transactions');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->getTable(), function (Blueprint $table) {
            $table->engine = "innoDB";
            $table->unsignedBigInteger('id', true);
            $table->enum('provider', \Parsisolution\Gateway\GatewayManager::availableDrivers());
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->nullable();
            $table->string('ref_id', 100)->nullable();
            $table->string('tracking_code', 50)->nullable();
            $table->string('card_number', 50)->nullable();
            $table->enum('status', \Parsisolution\Gateway\Transaction::availableStates())
                ->default(\Parsisolution\Gateway\Transaction::STATE_INIT);
            $table->string('ip', 20)->nullable();
            $table->json('extra')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->nullableTimestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop($this->getTable());
    }
}
