<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Module extends XmlElement
{
    public const TRANSLATABLE_FIELDS = ['label'];

    /**
     * @var array<string, string>
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $source = null;

    /**
     * @var string
     */
    protected $name;

    /**
     * @deprecated manifest:v2.0 will be required in future versions
     *
     * @var string|null
     */
    protected $parent = null;

    /**
     * @var int
     */
    protected $position = 1;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parse(\DOMElement $element): array
    {
        $values = [];

        if ($element->attributes instanceof \DOMNamedNodeMap) {
            foreach ($element->attributes as $attribute) {
                $values[self::kebabCaseToCamelCase($attribute->name)] = XmlReader::phpize($attribute->value);
            }
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);
            }
        }

        return $values;
    }
}
