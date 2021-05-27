<?php
namespace AditumPayment\Magento2\Model;

class ConfigProvider
{
    protected $scopeConfig;
    protected $assetRepo;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Asset\Repository $assetRepo
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepo = $assetRepo;
    }


    // Common functions

    public function getTermsUrl()
    {
        $fileName = "Termos-de-Uso-Portal-Aditum-V3-20210512.pdf";
        return $this->assetRepo->getUrl("AditumPayment_Magento2::pdf/".$fileName);
    }
    public function getTermsTxt()
    {
        return "Aceito os termos e condições";
    }
    public function getAntiFraudType()
    {
        $type_id = $this->scopeConfig->getValue("payment/aditum/antifraudtype");
        if($type_id==1){
            return "clearsale";
        }
        if($type_id==2){
            return "konduto";
        }
        return false;
    }
    public function getAntiFraudId()
    {
        return $this->scopeConfig->getValue("payment/aditum/antifraud_id");
    }
}
