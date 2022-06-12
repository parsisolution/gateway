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
            $table->unsignedTinyInteger('provider');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->nullable();
            $table->string('order_id', 100)->nullable()->unique();
            $table->string('token', 100)->nullable();
            $table->string('reference_id', 100)->nullable()->index();
            $table->string('trace_number', 100)->nullable()->index();
            $table->string('rrn', 100)->nullable();
            $table->string('card_number', 50)->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('ip', 20)->nullable();
            $table->json('extra')->nullable();
            $table->json('log')->nullable();
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
