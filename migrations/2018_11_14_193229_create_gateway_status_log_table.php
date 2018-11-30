<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGatewayStatusLogTable extends Migration
{

    private function getTable()
    {
        return config(\Parsisolution\Gateway\GatewayManager::CONFIG_FILE_NAME.'.table', 'gateway_transactions');
    }

    private function getLogTable()
    {
        return $this->getTable().'_logs';
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->getLogTable(), function (Blueprint $table) {
            $table->engine = "innoDB";
            $table->increments('id');
            $table->unsignedBigInteger('transaction_id');
            $table->string('result_code', 255)->nullable();
            $table->string('result_message', 255)->nullable();
            $table->timestamp('log_date')->nullable();

            $table
                ->foreign('transaction_id')
                ->references('id')
                ->on($this->getTable())
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop($this->getLogTable());
    }
}
