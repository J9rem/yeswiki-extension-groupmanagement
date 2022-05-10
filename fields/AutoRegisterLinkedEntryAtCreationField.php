<?php

namespace YesWiki\Groupmanagement\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\RadioEntryField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;

/**
 * @Field({"autoregisterlinkedentry"})
 */
class AutoRegisterLinkedEntryAtCreationField extends CheckboxField
{
    protected $linkedFieldName;
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->options = [
            'oui' => _t('YES'),
        ];
        $this->linkedFieldName = trim($this->listLabel);
        $this->name = trim($this->name);
    }

    protected function renderInput($entry)
    {
        // check if creation of the entry for a connected user
        $isCreation = !$entry;
        $user = $this->getService(UserManager::class)->getLoggedUser();
        if (!$isCreation || empty($user)) {
            return null;
        } else {
            return parent::renderInput($entry);
        }
    }

    protected function renderStatic($entry)
    {
        return null;
    }

    public function formatValuesBeforeSave($entry)
    {
        $user = $this->getService(UserManager::class)->getLoggedUser();
        if (!empty($user)) {
            $data = parent::formatValuesBeforeSave($entry);
            $value = $data[$this->propertyName];
            if ($value == "oui") {
                $this->registerUser($user, $entry);
            }
        }
        return [
            'fields-to-remove' => [
                $this->propertyName . self::FROM_FORM_ID,
                $this->propertyName
                ]];
    }

    public function getLinkedFieldName()
    {
        return $this->linkedFieldName;
    }

    public function jsonSerialize()
    {
        $array = array_merge(
            parent::jsonSerialize(),
            [
                'name' => '',
                'propertyname' => '',
                'linkedFieldName' => $this->getLinkedFieldName()
            ]
        );
        unset($array['options']);
        return $array;
    }

    protected function registerUser($user, $entry)
    {
        // getServices
        $formManager = $this->getService(FormManager::class);
        $entryManager = $this->getService(EntryManager::class);
        $pageManager = $this->getService(PageManager::class);
        $dbService = $this->getService(DbService::class);

        if (empty($this->name) || strval(intval($this->name)) != strval($this->name)) {
            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_FORM_ERROR')
            ]), 'error');
        } else {
            $form = $formManager->getOne($this->name);
            if (empty($form)) {
                flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                    'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_NO_FORM', [
                        'formId' => $this->name
                    ])
                ]), 'error');
            } else {
                $fieldName = $this->getLinkedFieldName();
                if (!empty($fieldName)) {
                    $field = $formManager->findFieldFromNameOrPropertyName($fieldName, $form['bn_id_nature']);
                    if (!empty($field) && (
                        $field instanceof RadioEntryField ||
                        $field instanceof SelectEntryField ||
                        $field instanceof CheckboxEntryField
                    )) {
                        if (trim($field->getLinkedObjectName()) == $entry['id_typeannonce']) {
                            $fields = [$field];
                        } else {
                            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                                'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_NO_FIELDTYPE', [
                                    'formId' => $this->name,
                                    'fieldName' => $fieldName,
                                    'currentFormId' => $entry['id_typeannonce']
                                ])
                            ]), 'error');
                        }
                    } else {
                        flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                            'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_NO_THIS_FIELDNAME', [
                                'formId' => $this->name,
                                'fieldName' => $fieldName
                            ])
                        ]), 'error');
                    }
                } else {
                    $fields = [];
                    foreach ($form['prepared'] as $field) {
                        if (
                            (
                                $field instanceof RadioEntryField ||
                            $field instanceof SelectEntryField ||
                            $field instanceof CheckboxEntryField
                            ) &&
                            (trim($field->getLinkedObjectName()) == $entry['id_typeannonce'])) {
                            $fields[] = $field;
                        }
                    }
                    if (empty($fields)) {
                        flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                            'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_NO_FIELDNAMES', [
                                'formId' => $this->name
                            ])
                        ]), 'error');
                    }
                }
                if (!empty($fields)) {
                    $entries = $entryManager->search([
                        'formsIds' => [$form['bn_id_nature']],
                        'user' => $user['name']
                    ]);
                    if (empty($entries)) {
                        flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_ERROR_MSG', [
                            'errorOrigin' => _t('CUSTOM_AUTOREGISTERLINKEDENTRY_NO_ENTRIES', [
                                'formId' => $this->name
                            ])
                        ]), 'warning');
                    } else {
                        $updatedEntries = [];
                        foreach ($entries as $entryToUpdate) {
                            $modified = false;
                            $error = false;
                            foreach ($fields as $field) {
                                $result = $this->updateValues($entryToUpdate, $field, $entry['id_fiche']);
                                $modified =  in_array($result, ["OK"]) ? true : $modified;
                                $error = !in_array($result, ["OK","already-ok"]) ? true : $error;
                            }
                            if ($modified && $this->updateEntry($entryToUpdate, $entryManager, $pageManager, $dbService) && !$error) {
                                $updatedEntries[] = $entryToUpdate['id_fiche'];
                            }
                        }
                        if ((0 < count($updatedEntries)) && (count($updatedEntries) < count($entries))) {
                            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_NOT_ALL_UPDATED_MSG', [
                                'entriesOK' => implode(",", $updatedEntries),
                                'entriesNotOk' => implode(",", array_map(function ($entryUpdated) {
                                    return $entryUpdated['id_fiche'];
                                }, array_filter($entries, function ($entryUpdated) use ($updatedEntries) {
                                    return !in_array($entryUpdated['id_fiche'], $updatedEntries);
                                })))
                            ]), 'error');
                        } elseif (count($updatedEntries) == 0) {
                            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_NOT_UPDATED_MSG', [
                                'entriesNotOk' => implode(",", array_map(function ($entryUpdated) {
                                    return $entryUpdated['id_fiche'];
                                }, $entries))
                            ]), 'error');
                        } elseif (count($updatedEntries) == 1) {
                            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_OK_ONE_MSG', [
                                'entry' => $entries[array_key_first($entries)]['id_fiche']
                            ]), 'success');
                        } else {
                            flash(_t('CUSTOM_AUTOREGISTERLINKEDENTRY_OK_MSG', [
                                'entries' => implode(",", array_map(function ($entryUpdated) {
                                    return $entryUpdated['id_fiche'];
                                }, $entries))
                            ]), 'success');
                        }
                    }
                }
            }
        }
    }

    protected function updateValues(&$entry, $field, $newValue): string
    {
        if (!($field instanceof RadioEntryField || $field instanceof SelectEntryField || $field instanceof CheckboxEntryField)) {
            return "error";
        } elseif (isset($entry[$field->getPropertyName()]) && in_array($newValue, explode(',', $entry[$field->getPropertyName()]))) {
            return "already-ok";
        } elseif (!$field->canEdit($entry)) {
            return "not-editable";
        } else {
            $entry[$field->getPropertyName()] = $field instanceof CheckboxEntryField
                ? (
                    empty($entry[$field->getPropertyName()])
                    ? $newValue
                    : "{$entry[$field->getPropertyName()]},$newValue"
                )
                : $newValue;
            return "OK";
        }
    }

    protected function updateEntry($data, $entryManager, $pageManager, $dbService):bool
    {
        try {
            $entryManager->validate(array_merge($data, ['antispam' => 1]));
        } catch (\Throwable $th) {
            return false;
        }

        $data['date_maj_fiche'] = date('Y-m-d H:i:s', time());

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }
        
        if (isset($data['sendmail'])) {
            unset($data['sendmail']);
        }

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map('utf8_encode', $data);
        }

        $oldPage = $pageManager->getOne($data['id_fiche']);
        $owner = $oldPage['owner'] ?? '';
        $user = $oldPage['user'] ?? '';

        // set all other revisions to old
        $dbService->query("UPDATE {$dbService->prefixTable('pages')} SET `latest` = 'N' WHERE `tag` = '{$dbService->escape($data['id_fiche'])}'");

        // add new revision
        return $dbService->query("INSERT INTO {$dbService->prefixTable('pages')} SET ".
            "`tag` = '{$dbService->escape($data['id_fiche'])}', ".
            "`time` = '{$dbService->escape($data['date_maj_fiche'])}', ".
            "`owner` = '{$dbService->escape($owner)}', ".
            "`user` = '{$dbService->escape($user)}', ".
            "`latest` = 'Y', ".
            "`body` = '" . $dbService->escape(json_encode($data)) . "', ".
            "`body_r` = ''") ? true : false;
    }
}
