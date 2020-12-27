<?
namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Config;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Request;
use Bitrix\Main\Result;
use Bitrix\Main\Text\Encoding;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

class CustomPaymentHandler extends PaySystem\ServiceHandler// implements PaySystem\IRefundExtended, PaySystem\IHold
{
	public function initiatePay(Payment $payment, Request $request = null)
	{
		$params = array(
			'PARAM1' => 'VALUE1',
			'PARAM2' => 'VALUE2',
		);
		$this->setExtraParams($params);
		return $this->showTemplate($payment, 'template');
	}
	
	public function getPaymentIdFromRequest(Request $request)
	{
		$paymentId = $request->get('ORDER');
		$paymentId = preg_replace("/^[0]+/", '', $paymentId);
		return intval($paymentId);
	}
	
	public function getCurrencyList()
	{
		$currencyList = [
			'RUB'
		];
		return $currencyList;
	}
	
	public static function getIndicativeFields()
	{
		return ['PARAM1','PARAM2'];
	}
	
	static protected function isMyResponseExtended(Request $request, $paySystemId)
	{
		return true;
	}
	
	public function processRequest(Payment $payment, Request $request)
	{
		$result = new PaySystem\ServiceResult();
		$action = $request->get('ACTION');
		$data = $this->extractDataFromRequest($request);
		
		$data['CODE'] = $action;
		
		if($action === '1') {
			$result->addError(new Error(Loc::getMessage('MSG_ERROR_PAYMENT')));
		} elseif($action === '0') {            
			$fields = [
				'PS_STATUS_CODE' => $action,
				'PS_STATUS_MESSAGE' => '',
				'PS_SUM' => $request->get('AMOUNT'),
				'PS_CURRENCY' => $payment->getField('CURRENCY'),
				'PS_RESPONSE_DATE' => new DateTime(),
				'PS_INVOICE_ID' => '',
			];            

			if ($this->isCorrectSum($payment, $request)) {
				$data['CODE'] = 0;
				$fields['PS_STATUS'] = 'Y';
				$fields['PS_STATUS_DESCRIPTION'] = Loc::getMessage('MSG_PAYMENT_SUCCESSFUL');
				$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
			} else {
				$data['CODE'] = 200;
				$fields['PS_STATUS'] = 'N';
				$message = Loc::getMessage('MSG_INVALID_PAYMENT_AMOUNT');
				$fields['PS_STATUS_DESCRIPTION'] = $message;
				$result->addError(new Error($message));
			}
			
			$result->setPsData($fields);
		} else {
			$result->addError(new Error(Loc::getMessage('MSG_INVALID_PAYMENT_STATUS')));
		}
		
		$result->setData($data);

		if (!$result->isSuccess()) {
			PaySystem\ErrorLog::add(array(
				'ACTION' => 'processRequest',
				'MESSAGE' => join('\n', $result->getErrorMessages())
			));
		}

		return $result;
	}
}
