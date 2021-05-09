<?php

namespace AditumPayment\Magento2\Model\Method;

use Magento\Directory\Helper\Data as DirectoryHelper;

class Boleto extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'aditumboleto';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canAuthorize = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = ['BRL'];
    protected $_infoBlockType = \AditumPayment\Magento2\Block\Info::class;
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];
    protected $adminSession;
    protected $messageManager;
    protected $api;
    protected $logger;
    protected $_scopeConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \AditumPayment\Magento2\Helper\Api $api,
        \Magento\Backend\Model\Auth\Session $adminSession,
        \Psr\Log\LoggerInterface $mlogger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig,
            $logger, $resource, $resourceCollection, $data, $directory);
        $this->api = $api;
        $this->adminSession = $adminSession;
        $this->logger = $mlogger;
        $this->_scopeConfig = $scopeConfig;
    }
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Inside Order');
        $this->logger->info(json_encode($payment->getAdditionalInformation(),true));
        $order = $payment->getOrder();
        if (!$result = $this->api->createOrderBoleto($order,$payment)) {
            throw new \Magento\Framework\Validator\Exception(__('Houve um erro processando seu pedido. Por favor entre em contato conosco.'));
        }
        $result = json_decode(json_encode($result),true);
        if ($result['status'] === "PreAuthorized") {
            throw new \Magento\Framework\Validator\Exception(__($this->api->getError($result)));
        }
//        throw new \Magento\Framework\Validator\Exception(__('Erro temporario'));
        $this->updateOrderRaw($order->getIncrementId());
//        $order->setExtOrderId($result);
        $order->addStatusHistoryComment('ID PIX: '.json_encode($result));
        $payment->setAdditionalInformation('uuid',$result['charge']['id']);
        $payment->setAdditionalInformation('aditumNumber',$result['charge']['transactions'][0]['aditumNumber']);
        $payment->setAdditionalInformation('transactionId',$result['charge']['transactions'][0]['transactionId']);
        $payment->setAdditionalInformation('digitalLine',$result['charge']['transactions'][0]['digitalLine']);
        $payment->setAdditionalInformation('barcode',$result['charge']['transactions'][0]['barcode']);
        $payment->setAdditionalInformation('bankSlipId',$result['charge']['transactions'][0]['bankSlipId']);
        $payment->setAdditionalInformation('bankIssuerId',$result['charge']['transactions'][0]['bankIssuerId']);

        $payment->setAdditionalInformation('boleto_url',$this->api->getBoletoUrl($result));
        return $this;
    }
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if(!$this->_scopeConfig->getValue('payment/aditum/enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        if(!$this->_scopeConfig->getValue('payment/aditum_boleto/enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)){
            return false;
        }
        $isAvailable = $this->getConfigData('active', $quote ? $quote->getStoreId() : null);
        if(!$isAvailable) return false;
        return true;
    }
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }
        try {
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
        $payment
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        return $this;
    }
    // Gambi master force update order
    public function updateOrderRaw($incrementId){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order');
        $sql = "UPDATE " . $tableName . " SET status = 'pending', state = 'new' WHERE entity_id = " . $incrementId;
        $connection->query($sql);
    }
}
