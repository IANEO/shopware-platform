<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class LineItemInCategoryRule extends Rule
{
    /**
     * @var array<string>
     */
    protected array $categoryIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string> $categoryIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, array $categoryIds = [])
    {
        parent::__construct();

        $this->categoryIds = $categoryIds;
        $this->operator = $operator;
    }

    public function getName(): string
    {
        return 'cartLineItemInCategory';
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfCategory($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            if ($this->matchesOneOfCategory($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['categoryIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfCategory(LineItem $lineItem): bool
    {
        return RuleComparison::uuids($lineItem->getPayloadValue('categoryIds'), $this->categoryIds, $this->operator);
    }
}
