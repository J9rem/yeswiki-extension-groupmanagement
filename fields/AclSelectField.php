<?php

namespace YesWiki\Groupmanagement\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\BazarField;

/**
 * @Field({"aclselect"})
 */
class AclSelectField extends BazarField
{
    protected $entryReadRight;
    protected $entryWriteRight;
    protected $entryCommentRight;
    protected $suffix;

    protected const FIELD_ENTRY_READ_RIGHT = 3;
    protected const FIELD_ENTRY_WRITE_RIGHT = 4;
    protected const FIELD_ENTRY_COMMENT_RIGHT = 8;
    protected const FIELD_SUFFIX_READ_GROUP = 9;

    protected const PUBLIC_VALUE = "public";
    protected const PROTECTED_VALUE = "protected";

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->size = null;
        $this->maxChars = null;
        $this->searchable = null;
        $this->size = null;
        $this->entryReadRight = $values[self::FIELD_ENTRY_READ_RIGHT];
        $this->entryWriteRight = $values[self::FIELD_ENTRY_WRITE_RIGHT];
        $this->entryCommentRight = $values[self::FIELD_ENTRY_COMMENT_RIGHT];
        $this->suffix = $values[self::FIELD_SUFFIX_READ_GROUP];
        $this->required = true;
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);
        return $this->render("@bazar/inputs/radio.twig", [
            'options' => [
                'public' => _t('GRPMNGT_ACLSELECT_PUBLIC'),
                'protected' => _t('GRPMNGT_ACLSELECT_LIMITED_TO_MEMBERS'),
            ],
            'value' => $this->getValue($entry),
            'displayFilterLimit' => false
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $wiki = $this->getWiki();
        if (empty($wiki->LoadAcl($entry['id_fiche'], 'read', false)['list'])) {
            $wiki->SaveAcl($entry['id_fiche'], 'read', $this->replaceWithCreator($this->entryReadRight, $entry));
        }
        if (empty($wiki->LoadAcl($entry['id_fiche'], 'write', false)['list'])) {
            $wiki->SaveAcl($entry['id_fiche'], 'write', $this->replaceWithCreator($this->entryWriteRight, $entry));
        }
        if (empty($GLOBALS['wiki']->LoadAcl($entry['id_fiche'], 'comment', false)['list'])) {
            $wiki->SaveAcl($entry['id_fiche'], 'comment', $this->replaceWithCreator($this->entryCommentRight, $entry));
        }
        $value = $this->getValue($entry);
        $readAcl = "{$entry['id_fiche']}{$this->getSuffix()}";
        $oldAcls = $wiki->LoadAcl($entry['id_fiche'], 'read', false)['list'];
        if ($value == self::PROTECTED_VALUE) {
            $oldAclsFiltered = array_filter(array_map('trim', explode("\n", str_replace(["\r\n","\r"], "\n", $oldAcls))), function ($line) {
                return !empty($line) && !in_array($line, ["*","+"]) && substr($line, 0, 1) != "!";
            });
            if (!in_array("@$readAcl", $oldAclsFiltered)) {
                $oldAclsFiltered[] = "@$readAcl";
            }
        } else {
            $oldAclsFiltered = array_filter(array_map('trim', explode("\n", str_replace(["\r\n","\r"], "\n", $oldAcls))), function ($line) {
                return !empty($line);
            });
            if (!in_array("*", $oldAclsFiltered)) {
                $oldAclsFiltered[] = "*";
            }
        }
        $newAcls = implode("\n", $oldAclsFiltered)."\n";
        $wiki->SaveAcl($entry['id_fiche'], 'read', $newAcls);

        return [
            $this->getPropertyName() => $value,
        ];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);
        return $this->render("@bazar/fields/text.twig", [
            'value' => ($value == self::PROTECTED_VALUE) ? _t('GRPMNGT_ACLSELECT_LIMITED_TO_MEMBERS') : _t('GRPMNGT_ACLSELECT_PUBLIC')
        ]);
    }

    protected function getValue($entry)
    {
        $value = parent::getValue($entry);
        return (empty($value) || $value != self::PROTECTED_VALUE) ? self::PUBLIC_VALUE : self::PROTECTED_VALUE;
    }

    private function replaceWithCreator($right, $entry)
    {
        // le signe # ou le mot user indiquent que le owner de la fiche sera utilisé pour les droits
        if ($right === 'user' or $right === '#') {
            return $entry['nomwiki'];
        }
        return $right;
    }

    public function getSuffix()
    {
        return $this->suffix;
    }

    
    public function jsonSerialize()
    {
        return [
            'id' => $this->getPropertyName(),
            'propertyname' => $this->getPropertyName(),
            'label' => $this->getLabel(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'default' => $this->getDefault(),
            'searchable' => $this->getSearchable(),
            'required' => $this->isRequired(),
            'helper' => $this->getHint(),
            'read_acl' => $this->getReadAccess(),
            'write_acl' => $this->getWriteAccess(),
            'sem_type' => $this->getSemanticPredicate(),
            'entryReadRight' => $this->entryReadRight,
            'entryWriteRight' => $this->entryWriteRight,
            'entryCommentRight' => $this->entryCommentRight,
            'suffix' => $this->getSuffix()
            ];
    }
}
