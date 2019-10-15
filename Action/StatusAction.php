<?php
namespace Crevillo\Payum\Redsys\Action;

use Crevillo\Payum\Redsys\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;

class StatusAction implements ActionInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{

    use GatewayAwareTrait;
    use GenericTokenFactoryAwareTrait;
    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request GetStatusInterface */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        if(isset($httpRequest->query['cancel'])) {
            $request->markCanceled();
            return;
        }

        if(isset($httpRequest->query['accept'])) {
            $request->markCaptured();
            return;
        }

        if (null == $model['Ds_Response']) {
            $request->markNew();

            return;
        }


        if ($model['Ds_AuthorisationCode'] && null === $model['Ds_Response']) {
            $request->markPending();

            return;
        }

        if (in_array($model['Ds_Response'],
            array(Api::DS_RESPONSE_CANCELED, Api::DS_RESPONSE_USER_CANCELED))) {
            $request->markCanceled();

            return;
        }

        if (0 <= $model['Ds_Response'] && 99 >= $model['Ds_Response']) {
            $request->markCaptured();

            return;
        }

        $request->markUnknown();
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
