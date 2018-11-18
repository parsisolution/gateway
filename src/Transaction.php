<?php

namespace Parsisolution\Gateway;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Parsisolution\Gateway\Transactions\RequestTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;


class Transaction {

    const STATE_INIT = 'INIT';
    const MESSAGE_INIT = 'تراکنش ایجاد شد.';

    const STATE_SUCCEEDED = 'SUCCEEDED';
    const MESSAGE_SUCCEEDED = 'پرداخت با موفقیت انجام شد.';

    const STATE_FAILED = 'FAILED';
    const MESSAGE_FAILED = 'عملیات پرداخت با خطا مواجه شد.';

    /**
     * Get all of the available transaction states.
     *
     * @return array
     */
    public static function availableStates()
    {
        return [
            self::STATE_INIT,
            self::STATE_SUCCEEDED,
            self::STATE_FAILED,
        ];
    }

    /**
     * ID of the transaction row in database
     *
     * @var string
     */
    protected $id;

    /**
     * database manager
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $db;

    /**
     * The Name of the transaction's table
     *
     * @var string
     */
    protected $table_name;

    /**
     * Transaction constructor.
     *
     * @param DatabaseManager $db
     * @param string $table_name
     */
    public function __construct(DatabaseManager $db, $table_name)
    {
        $this->db = $db;
        $this->table_name = $table_name;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Gets query builder for transactions table
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->db->table($this->table_name);
    }

    /**
     * Gets query builder for transaction logs table
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getLogTable()
    {
        return $this->db->table($this->table_name . '_logs');
    }

    private function generateTimeID()
    {
        $generateUid = function () {
            return substr(str_pad(str_replace('.', '', microtime(true)), 12, 0), 0, 12);
        };
        $uid = $generateUid();
        while ($this->getTable()->where('id', $uid)->first())
            $uid = $generateUid();

        return $uid;
    }

    /**
     * Insert new transaction into transactions table
     * and return the its id
     *
     * @param RequestTransaction $transaction
     * @param string $provider
     * @param null|string $client_ip
     * @return string
     */
    public function create(RequestTransaction $transaction, $provider, $client_ip)
    {
        $uid = $this->generateTimeID();

        $this->getTable()->insert([
            'id'         => $uid,
            'provider'   => $provider,
            'amount'     => $transaction->getAmount()->getTotal(),
            'currency'   => $transaction->getAmount()->getCurrency(),
            'status'     => self::STATE_INIT,
            'ip'         => $client_ip,
            'extra'      => json_encode($transaction->getExtra()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->id = $uid;

        return $uid;
    }

    /**
     * Get current transaction db model
     *
     * @return \stdClass
     */
    public function get()
    {
        return $this->getTable()->where('id', $this->id)->first();
    }

    /**
     * Update transaction reference ID
     *
     * @param string $referenceId
     * @return int
     */
    public function updateReferenceId($referenceId)
    {
        return $this->getTable()->where('id', $this->id)->update([
            'ref_id'     => $referenceId,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Settle transaction
     * Set status to success status
     *
     * @param SettledTransaction $transaction
     * @return bool
     */
    public function succeeded(SettledTransaction $transaction)
    {
        return $this->getTable()->where('id', $this->id)->update([
            'status'        => self::STATE_SUCCEEDED,
            'tracking_code' => $transaction->getTrackingCode(),
            'card_number'   => $transaction->getCardNumber(),
            'payment_date'  => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);
    }

    /**
     * Failed transaction
     * Set status to failure status
     *
     * @return bool
     */
    public function failed()
    {
        return $this->getTable()->where('id', $this->id)->update([
            'status'       => self::STATE_FAILED,
            'updated_at'   => Carbon::now(),
        ]);
    }

    /**
     * Create new log
     *
     * @param string|int $statusCode
     * @param string $statusMessage
     * @return bool
     */
    public function createLog($statusCode, $statusMessage)
    {
        return $this->getLogTable()->insert([
            'transaction_id' => $this->id,
            'result_code'    => $statusCode,
            'result_message' => $statusMessage,
            'log_date'       => Carbon::now(),
        ]);
    }
}