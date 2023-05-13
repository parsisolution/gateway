<?php

namespace Parsisolution\Gateway\Providers\Pasargad\Utilities;

use XMLWriter;

class XMLGenerator
{
    public static function generateMultiPayment(array $paymentItems): ?string
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString(' ');

        if ($xmlWriter) {
            $xmlWriter->startDocument('1.0', 'UTF-8');

            foreach ($paymentItems as $item) {
                // <item>
                $xmlWriter->startElement('item');

                // <iban>
                $xmlWriter->startElement('iban');
                $xmlWriter->text($item['IBAN']);
                $xmlWriter->endElement();
                // </iban>

                // <type>
                $xmlWriter->startElement('type');
                $xmlWriter->text($item['type']);
                $xmlWriter->endElement();
                // </type>

                // <value>
                $xmlWriter->startElement('value');
                $xmlWriter->text($item['value']);
                $xmlWriter->endElement();
                // </value>

                $xmlWriter->endElement();
                // </item>
            }

            $xmlWriter->endDocument();

            return $xmlWriter->outputMemory(true);
        }

        return null;
    }

    public static function generateSubPaymentList(array $subPayments): ?string
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString(' ');

        if ($xmlWriter) {
            $xmlWriter->startDocument('1.0', 'UTF-8');

            // <SubPaymentList>
            $xmlWriter->startElement('SubPaymentList');

            // <SubPayments>
            $xmlWriter->startElement('SubPayments');

            foreach ($subPayments as $subPayment) {
                // <SubPayment>
                $xmlWriter->startElement('SubPayment');

                // <SubPayID>
                $xmlWriter->startElement('SubPayID');
                $xmlWriter->text($subPayment['SubPayID']);
                $xmlWriter->endElement();
                // </SubPayID>

                // <Amount>
                $xmlWriter->startElement('Amount');
                $xmlWriter->text($subPayment['Amount']);
                $xmlWriter->endElement();
                // </Amount>

                // <Date>
                $xmlWriter->startElement('Date');
                $xmlWriter->text($subPayment['Date']);
                $xmlWriter->endElement();
                // </Date>

                // <Account>
                $xmlWriter->startElement('Account');
                $xmlWriter->text($subPayment['Account']);
                $xmlWriter->endElement();
                // </Account>

                // <Description>
                $xmlWriter->startElement('Description');
                $xmlWriter->text($subPayment['Description'] ?? '');
                $xmlWriter->endElement();
                // </Description>

                $xmlWriter->endElement();
                // </SubPayment>
            }

            $xmlWriter->endElement();
            // </SubPayments>

            $xmlWriter->endElement();
            // </SubPaymentList>

            $xmlWriter->endDocument();

            return $xmlWriter->outputMemory(true);
        }

        return null;
    }

    public static function generateInvoiceUpdateList(string $invoiceUID, array $actions): ?string
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        $xmlWriter->setIndentString(' ');

        if ($xmlWriter) {
            $xmlWriter->startDocument('1.0', 'UTF-8');

            // <invoiceUpdateList>
            $xmlWriter->startElement('invoiceUpdateList');

            $xmlWriter->startElement('invoiceAction');
            $xmlWriter->startAttribute('invoiceUID');
            $xmlWriter->text($invoiceUID);
            $xmlWriter->endAttribute();

            foreach ($actions as $action) {
                // <action>
                $xmlWriter->startElement('action');

                // type=
                $xmlWriter->startAttribute('type');
                $xmlWriter->text($action['type']);
                $xmlWriter->endAttribute();

                // subPayID=
                $xmlWriter->startAttribute('subPayID');
                $xmlWriter->text($action['subPayID']);
                $xmlWriter->endAttribute();

                // amount=
                $xmlWriter->startAttribute('amount');
                $xmlWriter->text($action['amount']);
                $xmlWriter->endAttribute();

                // date=
                $xmlWriter->startAttribute('date');
                $xmlWriter->text($action['date']);
                $xmlWriter->endAttribute();

                // account=
                $xmlWriter->startAttribute('account');
                $xmlWriter->text($action['account']);
                $xmlWriter->endAttribute();

                $xmlWriter->endElement();
                // </action>
            }

            $xmlWriter->endElement();
            // </invoiceUpdateList>

            $xmlWriter->endDocument();

            return $xmlWriter->outputMemory(true);
        }

        return null;
    }
}
