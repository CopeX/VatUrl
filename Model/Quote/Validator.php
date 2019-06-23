<?php

namespace CopeX\VatUrl\Model\Quote;
use Magento\Quote\Observer\Frontend\Quote\Address\VatValidator as VatValidator;

class Validator extends VatValidator
{
    public function validate(\Magento\Quote\Model\Quote\Address $quoteAddress, $store)
    {
        $customerCountryCode = $quoteAddress->getCountryId();
        $customerVatNumber = $quoteAddress->getVatId();

        $merchantCountryCode = $this->customerVat->getMerchantCountryCode();
        $merchantVatNumber = $this->customerVat->getMerchantVatNumber();

        $validationResult = null;
        if ($this->customerAddress->hasValidateOnEachTransaction(
                $store
            ) ||
            $customerCountryCode != $quoteAddress->getValidatedCountryCode() ||
            $customerVatNumber != $quoteAddress->getValidatedVatNumber()
        ) {
            // Send request to gateway
            $validationResult = $this->customerVat->checkVatNumber(
                $customerCountryCode,
                $customerVatNumber,
                $merchantVatNumber !== '' ? $merchantCountryCode : '',
                $merchantVatNumber
            );
            /** add this here to fix checkout error see :https://github.com/magento/magento2/issues/12612 */
            if (is_array($quoteAddress->getRegion())) {
                $regionData = $quoteAddress->getRegion();
                if (array_key_exists('region_code', $regionData)) {
                    $quoteAddress->setRegionCode($regionData['region_code']);
                }
                if (array_key_exists('region_id', $regionData)) {
                    $quoteAddress->setRegionId($regionData['region_id']);
                }
                $quoteAddress->setRegion(null);
            }
            /** end */

            // Store validation results in corresponding quote address
            $quoteAddress->setVatIsValid((int)$validationResult->getIsValid());
            $quoteAddress->setVatRequestId($validationResult->getRequestIdentifier());
            $quoteAddress->setVatRequestDate($validationResult->getRequestDate());
            $quoteAddress->setVatRequestSuccess($validationResult->getRequestSuccess());
            $quoteAddress->setValidatedVatNumber($customerVatNumber);
            $quoteAddress->setValidatedCountryCode($customerCountryCode);
            $quoteAddress->save();
        } else {
            // Restore validation results from corresponding quote address
            $validationResult = new \Magento\Framework\DataObject(
                [
                    'is_valid'           => (int)$quoteAddress->getVatIsValid(),
                    'request_identifier' => (string)$quoteAddress->getVatRequestId(),
                    'request_date'       => (string)$quoteAddress->getVatRequestDate(),
                    'request_success'    => (bool)$quoteAddress->getVatRequestSuccess(),
                ]
            );
        }

        return $validationResult;
    }
}
