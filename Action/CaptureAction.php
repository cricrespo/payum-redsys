<?php
namespace Crevillo\Payum\Redsys\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Request\Capture;
use Crevillo\Payum\Redsys\Api;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryInterface;

class CaptureAction implements ActionInterface, ApiAwareInterface, GenericTokenFactoryAwareInterface
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var GenericTokenFactoryInterface
     */
    protected $tokenFactory;

    /**
     * @param GenericTokenFactoryInterface $genericTokenFactory
     *
     * @return void
     */
    public function setGenericTokenFactory(
        GenericTokenFactoryInterface $genericTokenFactory = null
    ) {
        $this->tokenFactory = $genericTokenFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function setApi($api)
    {
        if (false === $api instanceof Api) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request Capture */
        RequestNotSupportedException::assertSupports($this, $request);

        $postData = ArrayObject::ensureArrayObject($request->getModel());

        if (empty($postData['Ds_Merchant_MerchantURL']) && $request->getToken() && $this->tokenFactory) {
            $notifyToken = $this->tokenFactory->createNotifyToken(
                $request->getToken()->getGatewayName(),
                $request->getToken()->getDetails()
            );

            $postData['Ds_Merchant_MerchantURL'] = $notifyToken->getTargetUrl();
        }

        $urlOk = $this->addQueryParameter($request->getToken()->getAfterUrl(), 'accept');
        $urlCancel = $this->addQueryParameter($request->getToken()->getAfterUrl(), 'cancel');

        $postData->validatedKeysSet(array(
            'Ds_Merchant_Amount',
            'Ds_Merchant_Order',
            'Ds_Merchant_Currency',
            'Ds_Merchant_TransactionType',
            'Ds_Merchant_MerchantURL',
        ));

        $details['Ds_Merchant_MerchantCode'] = $this->api->getMerchantCode();
        $details['Ds_Merchant_Terminal'] = $this->api->getMerchantTerminalCode();

        if (false == $postData['Ds_Merchant_UrlOK'] && $request->getToken()) {
            $postData['Ds_Merchant_UrlOK'] = $urlOk;
        }
        if (false == $postData['Ds_Merchant_UrlKO'] && $request->getToken()) {
            $postData['Ds_Merchant_UrlKO'] = $urlCancel;
        }

        $details['Ds_SignatureVersion'] = Api::SIGNATURE_VERSION;
        $Ds_Merchant_Order = intval($request->getFirstModel()->getId());
        $Ds_Merchant_Order = strval($Ds_Merchant_Order);
        $postData['Ds_Merchant_Order'] = '0000'.$Ds_Merchant_Order;

        if (false == $postData['Ds_MerchantParameters'] && $request->getToken()) {
            $details['Ds_MerchantParameters'] = $this->api->createMerchantParameters($postData->toUnsafeArray());
        }

        if (false == $postData['Ds_Signature']) {
            $details['Ds_Signature'] = $this->api->sign($postData->toUnsafeArray());

            throw new HttpPostRedirect($this->api->getRedsysUrl(), $details);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }

    function unparse_url($parsed_url) {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    function addQueryParameter($url, $query)
    {
        $url = parse_url($url);
        $queryParameter = [];
        parse_str($url['query'], $queryParameter);
        $queryParameter[''.$query.''] = 1;
        $url['query'] = http_build_query($queryParameter);
        return $this->unparse_url($url);
    }
}
