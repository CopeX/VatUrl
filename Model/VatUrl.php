<?php

namespace CopeX\VatUrl\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class VatUrl extends \Magento\Customer\Model\Vat
{
    const VAT_VALIDATION_WSDL_URL = 'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    public function checkVatNumber($countryCode, $vatNumber, $requesterCountryCode = '', $requesterVatNumber = '')
    {
        // Default response
        $gatewayResponse = new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Error during VAT Number verification.'),
        ]);

        if (!extension_loaded('soap')) {
            $this->logger->critical(new LocalizedException(__('PHP SOAP extension is required.')));
            return $gatewayResponse;
        }

        if (!$this->canCheckVatNumber($countryCode, $vatNumber, $requesterCountryCode, $requesterVatNumber)) {
            return $gatewayResponse;
        }

        try {
            $soapClient = $this->createVatNumberValidationSoapClient();

            $requestParams = [];
            $requestParams['countryCode'] = $countryCode;
            $vatNumberSanitized = $this->isCountryInEU($countryCode)
                ? str_replace([' ', '-', $countryCode], ['', '', ''], $vatNumber)
                : str_replace([' ', '-'], ['', ''], $vatNumber);
            $requestParams['vatNumber'] = $vatNumberSanitized;
            $requestParams['requesterCountryCode'] = $requesterCountryCode;
            $reqVatNumSanitized = $this->isCountryInEU($requesterCountryCode)
                ? str_replace([' ', '-', $requesterCountryCode], ['', '', ''], $requesterVatNumber)
                : str_replace([' ', '-'], ['', ''], $requesterVatNumber);
            $requestParams['requesterVatNumber'] = $reqVatNumSanitized;
            // Send request to service
            $result = $soapClient->checkVatApprox($requestParams);

            $gatewayResponse->setIsValid((bool)$result->valid);
            $gatewayResponse->setRequestDate((string)$result->requestDate);
            $gatewayResponse->setRequestIdentifier((string)$result->requestIdentifier);
            $gatewayResponse->setRequestSuccess(true);

            if ($gatewayResponse->getIsValid()) {
                $gatewayResponse->setRequestMessage(__('VAT Number is valid.'));
            } else {
                $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number.'));
            }
        } catch (\Exception $exception) {
            $gatewayResponse->setIsValid(false);
            $gatewayResponse->setRequestDate('');
            $gatewayResponse->setRequestIdentifier('');
        }

        return $gatewayResponse;
    }

    protected function createVatNumberValidationSoapClient($trace = false)
    {
        return new \SoapClient(self::VAT_VALIDATION_WSDL_URL, ['trace' => $trace]);
    }
}
