<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class PaymentDistinguishableNameGenerator
{
    private EntityRepository $paymentMethodRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $paymentMethodRepository
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function generateDistinguishablePaymentNames(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $payments = $this->getInstalledPayments($context);

            $upsertablePayments = $this->generateDistinguishableNamesPayload($payments);

            $this->paymentMethodRepository->upsert($upsertablePayments, $context);
        });
    }

    private function getInstalledPayments(Context $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('translations')
            ->addAssociation('plugin.translations')
            ->addAssociation('appPaymentMethod.app.translations');

        /** @var PaymentMethodCollection $payments */
        $payments = $this->paymentMethodRepository
            ->search($criteria, $context)
            ->getEntities();

        return $payments;
    }

    private function generateDistinguishableNamesPayload(PaymentMethodCollection $payments): array
    {
        $upsertablePayments = [];
        foreach ($payments as $payment) {
            if ($payment->getTranslations() === null) {
                continue;
            }

            foreach ($payment->getTranslations() as $translation) {
                $languageId = $translation->getLanguageId();
                $paymentName = $translation->getName() ?? $payment->getTranslation('name');

                if ($payment->getPluginId() === null && $payment->getAppPaymentMethod() === null) {
                    continue;
                }

                $upsertablePayments[] = [
                    'id' => $payment->getId(),
                    'distinguishableName' => [
                        $languageId => $this->generatePluginBasedName($payment, $languageId, $paymentName)
                            ?? $this->generateAppBasedName($payment, $languageId, $paymentName),
                    ],
                ];
            }
        }

        return $upsertablePayments;
    }

    private function generatePluginBasedName(PaymentMethodEntity $payment, string $languageId, string $paymentName): ?string
    {
        if ($payment->getPlugin() === null) {
            return null;
        }

        if ($payment->getPlugin()->getTranslations() === null) {
            return null;
        }

        $pluginLabel = $payment->getPlugin()->getTranslations()->filterByProperty('languageId', $languageId)->first()
            ? $payment->getPlugin()->getTranslations()->filterByProperty('languageId', $languageId)->first()->getLabel()
            : $payment->getPlugin()->getTranslation('label');

        return sprintf(
            '%s | %s',
            $paymentName,
            $pluginLabel
        );
    }

    private function generateAppBasedName(PaymentMethodEntity $payment, string $languageId, string $paymentName): ?string
    {
        if ($payment->getAppPaymentMethod() === null) {
            return null;
        }

        if ($payment->getAppPaymentMethod()->getApp() === null) {
            return null;
        }

        if ($payment->getAppPaymentMethod()->getApp()->getTranslations() === null) {
            return null;
        }

        $appLabel = $payment->getAppPaymentMethod()->getApp()->getTranslations()->filterByProperty('languageId', $languageId)->first()
            ? $payment->getAppPaymentMethod()->getApp()->getTranslations()->filterByProperty('languageId', $languageId)->first()->getLabel()
            : $payment->getAppPaymentMethod()->getApp()->getTranslation('label');

        return sprintf(
            '%s | %s',
            $paymentName,
            $appLabel
        );
    }
}
