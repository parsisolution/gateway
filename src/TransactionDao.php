<?php

namespace Parsisolution\Gateway;

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Parsisolution\Gateway\Contracts\HasId;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\RequestTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class TransactionDao
{

    const STATE_INIT = 0;
    const MESSAGE_INIT = 'تراکنش ایجاد شد.';

    const STATE_SUCCEEDED = 1;
    const MESSAGE_SUCCEEDED = 'پرداخت با موفقیت انجام شد.';

    const STATE_FAILED = 2;
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
     * Gets query builder for transactions table
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->db->table($this->table_name);
    }

    /**
     * Insert new transaction into transactions table
     * and return its id
     *
     * @param RequestTransaction $transaction
     * @param integer $provider
     * @param null|string $client_ip
     * @return UnAuthorizedTransaction
     */
    public function create(RequestTransaction $transaction, $provider, $client_ip)
    {
        $generateUid = function () {
            $strReplace = str_replace('.', '', microtime(true));
            $string = str_pad($strReplace, 12, 0);

            return substr($string, 0, 12);
        };
        $uid = $generateUid();
        while ($this->getTable()->where('order_id', $uid)->first()) {
            $uid = $generateUid();
        }

        $fields = array_merge($transaction->getRaw(), [
            'provider'   => $provider,
            'amount'     => $transaction->getAmount()->getTotal(),
            'currency'   => $transaction->getAmount()->getCurrency(),
            'order_id'   => $uid,
            'status'     => self::STATE_INIT,
            'ip'         => $client_ip,
            'extra'      => json_encode($transaction->getExtra()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $id = $this->getTable()->insertGetId($fields);

        return new UnAuthorizedTransaction($transaction, $id, $uid);
    }

    /**
     * Update transaction reference ID
     *
     * @param AuthorizedTransaction $transaction
     * @return int
     */
    public function updateTransaction($transaction)
    {
        return $this->getTable()->where('id', $transaction->getId())->update([
            'reference_id' => $transaction->getReferenceId(),
            'token'        => $transaction->getToken(),
            'updated_at'   => Carbon::now(),
        ]);
    }

    /**
     * Determines if transaction is spent before
     *
     * @param SettledTransaction $transaction
     * @return bool
     */
    public function isSpent(SettledTransaction $transaction)
    {
        return !!$this->getTable()->where('trace_number', $transaction->getTraceNumber())->count();
    }

    /**
     * Settle transaction
     * Set status to success status
     *
     * @param SettledTransaction $transaction
     * @param array $additionalFields
     *
     * @return bool
     */
    public function succeeded(SettledTransaction $transaction, $additionalFields = [])
    {
        $fields = array_merge($additionalFields, [
            'status'       => self::STATE_SUCCEEDED,
            'reference_id' => $transaction->getReferenceId(),
            'trace_number' => $transaction->getTraceNumber(),
            'card_number'  => $transaction->getCardNumber(),
            'rrn'          => $transaction->getRRN(),
            'extra'        => json_encode($transaction->getExtra()),
            'log'          => json_encode([
                'result_code'    => self::STATE_SUCCEEDED,
                'result_message' => self::MESSAGE_SUCCEEDED,
                'logged_at'      => Carbon::now(),
            ]),
            'paid_at'      => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        return $this->getTable()->where('id', $transaction->getId())->update($fields);
    }

    /**
     * Failed transaction
     * Set status to failure status
     *
     * @param HasId $transaction
     * @param string|int $statusCode
     * @param string $statusMessage
     * @param null $referenceId
     *
     * @return bool
     */
    public function failed(HasId $transaction, $statusCode, $statusMessage, $referenceId = null)
    {
        $fields = [
            'status'     => self::STATE_FAILED,
            'log'        => json_encode([
                'result_code'    => $statusCode,
                'result_message' => $statusMessage,
                'logged_at'      => Carbon::now(),
            ]),
            'updated_at' => Carbon::now(),
        ];
        if ($referenceId) {
            $fields['reference_id'] = $referenceId;
        }

        return $this->getTable()->where('id', $transaction->getId())->update($fields);
    }
}
