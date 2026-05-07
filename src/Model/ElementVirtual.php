<?php

namespace DNADesign\ElementalVirtual\Model;

use Override;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;

/**
 * Virtual Linked Element.
 *
 * As elemental is based on a natural has_one relation to an object,
 * this allows the same element to be linked to multiple pages.
 *
 * @method LinkedElement() BaseElement
 */
class ElementVirtual extends BaseElement
{
    private static $icon = 'font-icon-block-virtual-page';

    private static $has_one = [
        'LinkedElement' => BaseElement::class
    ];

    /**
     * @var string
     */
    private static $class_description = 'Reused element';

    private static $table_name = 'ElementVirtual';

    private static $singular_name = 'Virtual block';

    private static $inline_editable = true;

    private static $controller_template = 'ElementHolder_VirtualLinked';

    /**
     * @config
     *
     * @var string A field to use as the title in the linkable dropdown. Must be a DatabaseField on BaseElement
     */
    private static $linkable_title_field = 'VirtualLookupTitle';

    /**
     * @param BaseElement
     * @param boolean $isSingleton
     * @param DataModel $model
     */
    public function __construct($record = null, $isSingleton = false, $model = null)
    {
        parent::__construct($record, $isSingleton, $model);

        $this->LinkedElement()->setVirtualOwner($this);
    }

    #[Override]
    public function getCMSFields()
    {
        $invalid = $this->isInvalidPublishState();

        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($invalid) {
            $fields->removeByName('Title');

            if ($invalid) {
                $warning = _t(
                    self::class . '.InvalidPublishStateWarning',
                    'Error: The original element is not published. This element will not work on the live site until ' .
                        'you click the link below and publish it.'
                );

                $fields->addFieldToTab(
                    'Root.Main',
                    LiteralField::create(
                        'WarningHeader',
                        '<p class="message error">' . $warning . '</p>'
                    )
                );
            }

            if ($this->LinkedElementID) {
                $message = sprintf(
                    '<p>%s</p><p><a href="%2$s" target="_blank">Click here to edit the original</a></p>',
                    _t(self::class . '.VirtualDescription', 'This is a virtual copy of an element.'),
                    $this->LinkedElement()->getEditLink()
                );

                $fields->addFieldToTab('Root.Main', LiteralField::create('Existing', $message));
            }
        });

        $fields = parent::getCMSFields();

        if ($fields->dataFieldByName('LinkedElementID')) {
            $fields->replaceField(
                'LinkedElementID',
                DropdownField::create(
                    'LinkedElementID',
                    _t(self::class . '.LinkedElement', 'Linked Element'),
                    BaseElement::get()->filter(['AvailableGlobally' => 1])
                        ->sort(['VirtualLookupTitle' => 'ASC'])
                        ->exclude(['ClassName' => ElementVirtual::class])
                        ->map('ID', 'VirtualLookupTitle')
                )
            );
        }

        return $fields;
    }

    /**
     * @return string
     */
    #[Override]
    public function getType()
    {
        return _t(self::class . '.BlockType', 'Virtual Block');
    }

    /**
     * Detect when a user has published a linked element but has not published
     * the LinkedElement.
     *
     * @return boolean
     */
    public function isInvalidPublishState(): bool
    {
        $element = $this->LinkedElement();

        return (!$element->isPublished() && $this->isPublished());
    }

    /**
     * Get a unique anchor name.
     *
     * @return string
     */
    #[Override]
    public function getAnchor(): string
    {
        $linkedElement = $this->LinkedElement();

        if ($linkedElement && $linkedElement->exists()) {
            return $linkedElement->getAnchor();
        }

        return 'e' . $this->ID;
    }

    /**
     * @return string
     */
    #[Override]
    public function getSummary(): ?string
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->getSummary();
        }
    }

    /**
     * @return string
     */
    #[Override]
    public function getTitle(): ?string
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->Title;
        }
    }

    /**
     * Override to render template based on LinkedElement
     */
    #[Override]
    public function forTemplate($holder = true): string
    {
        if ($linked = $this->LinkedElement()) {
            return $linked->forTemplate($holder);
        }

        return '';
    }

    #[Override]
    protected function provideBlockSchema(): array
    {
        $blockSchema = parent::provideBlockSchema();
        $blockSchema['content'] = $this->getSummary();
        return $blockSchema;
    }
}
