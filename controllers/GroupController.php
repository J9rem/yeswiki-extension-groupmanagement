<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Controller;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\RadioEntryField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Comschange\Service\CommentService;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiEventCompilerPass;
use YesWiki\Core\YesWikiController;
use YesWiki\Groupmanagement\Entity\DataContainer;
use YesWiki\Groupmanagement\Service\EventDispatcherInterface;
use YesWiki\Wiki;

class GroupController extends YesWikiController implements EventSubscriberInterface
{
    public const GROUP_TRIPLE_PROPERTY = "https://yeswiki.net/vocabulary/groupmanagementoptions";

    protected $aclService;
    protected $eventDispatcher;
    protected $formManager;
    protected $params;
    protected $templateEngine;
    protected $tripleStore;
    protected $userManager;

    public function __construct(
        AclService $aclService,
        EventDispatcherInterface $eventDispatcher,
        FormManager $formManager,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        TripleStore $tripleStore,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->eventDispatcher = $eventDispatcher;
        $this->formManager = $formManager;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    public static function getSubscribedEvents()
    {
        return [
            'groupmanagement.bazarliste.entriesready' => 'displayOnlyWritable',
            'groupmanagement.bazarliste.afterdynamicquery' => 'keepOnlyWritableIntoResponse',
        ];
    }
    public function displayOnlyWritable($event)
    {
        $eventData = $event->getData();
        if (!empty($eventData) && is_array($eventData) && isset($eventData['dataContainer']) && ($eventData['dataContainer'] instanceof DataContainer)) {
            $bazarData = $eventData['dataContainer']->getData();
            $arg = $bazarData['param'] ?? [];
            $keepOnlyEntriesWhereCanEdit = $arg['keeponlyentrieswherecanedit'] ?? false;
            if ($keepOnlyEntriesWhereCanEdit) {
                foreach ($bazarData['fiches'] as $idx => $entry) {
                    if (empty($entry['id_fiche']) || !$this->aclService->hasAccess('write', $entry['id_fiche'])) {
                        unset($bazarData['fiches'][$idx]);
                    }
                }
                $eventData['dataContainer']->setData($bazarData);
            }
        }
    }


    public function keepOnlyWritableIntoResponse($event)
    {
        if (!$this->wiki->UserIsAdmin()) {
            $keepOnlyEntriesWhereCanEdit = in_array($_GET['keeponlyentrieswherecanedit'] ?? false, [1,true,"1","true"], true);
            if ($keepOnlyEntriesWhereCanEdit) {
                $eventData = $event->getData();
                if (!empty($eventData) &&
                    is_array($eventData) &&
                    isset($eventData['response']) &&
                    method_exists($eventData['response'], 'getContent')) {
                    $response = $eventData['response'];
                    $status = $response->getStatusCode();
                    if ($status < 400) {
                        $content = $response->getContent();
                        $contentDecoded = json_decode($content, true);
                        if (!empty($contentDecoded) && !empty($contentDecoded['entries'])) {
                            $fieldMapping = $contentDecoded['fieldMapping'] ?? [];
                            $idFicheIdx = array_search("id_fiche", $fieldMapping);
                            if ($idFicheIdx !== false && $idFicheIdx > -1) {
                                $contentDecoded['entries'] = array_values(array_filter($contentDecoded['entries'], function ($entry) use ($idFicheIdx) {
                                    return !empty($entry[$idFicheIdx]) && $this->aclService->hasAccess('write', $entry[$idFicheIdx]);
                                }));
                                $response->setData($contentDecoded);
                            }
                        }
                    }
                }
            }
        }
    }

    public function defineBazarListeActionParams(array $arg, array $get, $callable): array
    {
        $isDynamic = (
            isset($arg['dynamic']) && (
                (is_bool($arg['dynamic']) && $arg['dynamic']) ||
                (!is_bool($arg['dynamic']) && !empty($arg['dynamic']) && !in_array($arg['dynamic'], [0,'0','no','non','false'], true))
            )
        );
        $template = $get['template'] ?? $arg['template'] ?? null;
        $template = is_string($template) ? $template : null;
        if (($template === 'calendar.tpl.html' && !$this->templateEngine->hasTemplate("@bazar/{$template}")) ||
            ($template === 'calendar' && !$this->templateEngine->hasTemplate("@bazar/{$template}.tpl.html"))) {
            $template = "calendar";
            $isDynamic = true;
        }
        $isDynamic = $isDynamic || in_array($template, ['card','list']);
        list('replaceTemplate'=>$replaceTemplate, 'options'=>$options) = $callable($isDynamic, $this->wiki->UserIsAdmin(), $arg);
        if (!is_array($options)) {
            $options = [];
        }
        if ($replaceTemplate) {
            $newTemplate = in_array($template, ["map","map.tpl.html","gogocarto","gogocarto.tpl.html"]) ? $template : "groupmanagement_pre_template.tpl.html";
            return [
                'template' => $newTemplate,
                'previous-template' => ($template != "groupmanagement_pre_template.tpl.html") ? $template : ($arg['previous-template'] ?? null),
            ]+$options;
        } else {
            return $options;
        }
    }

    public function renderTemplate(array $data, string $file = "", array $bazarPaths = []): string
    {
        if (!isset($data['param'])) {
            $data['param'] = [];
        }
        $param = $data['param'];
        $template = $param['template'] ?? "";
        $isDirect = false;
        if ($template == "groupmanagement_pre_template.tpl.html") {
            $template = $param['previous-template'] ?? "";
        } elseif (!empty($file) && !empty($bazarPaths)) {
            $formattedFolderName = str_replace("\\", "/", dirname($file));
            if (preg_match("/.*tools\/([a-z0-9]+)\/templates\/bazar$/", $formattedFolderName, $matches)) {
                $currentRelativeDir = "tools/{$matches[1]}/templates/bazar";
                $isDirect = true;
            } elseif (preg_match("/.*themes\/([A-Za-z0-9_\-]+)\/tools\/bazar(?:\/(templates))?\/$/", $formattedFolderName, $matches)) {
                $currentRelativeDir = (empty($matches[2]))
                    ? "themes/{$matches[1]}/tools/bazar"
                    : "themes/{$matches[1]}/tools/bazar/templates" ;
                $isDirect = true;
            } else {
                $paths = [
                    "custom/themes/tools/bazar/templates",
                    "custom/templates/bazar/templates",
                    "custom/templates/bazar",
                    "templates/bazar/templates",
                    "templates/bazar",
                    "themes/tools/bazar/templates",
                    "themes/tools/bazar",
                    "tools/bazar/presentation/templates",
                    "tools/bazar/templates",
                ];
                foreach ($paths as $path) {
                    if (substr($formattedFolderName, -strlen($path)) == $path) {
                        $currentRelativeDir = $path;
                        $isDirect = true;
                        break;
                    }
                }
            }
            if ($isDirect) {
                $curPos = array_search($currentRelativeDir, $paths);
                if ($curPos !== false) {
                    $fileName = basename($file);
                    for ($i=($curPos+1); $i < count($paths); $i++) {
                        if ($paths[$i] != $currentRelativeDir && $this->templateEngine->hasTemplate("{$paths[$i]}/$fileName")) {
                            $template = "{$paths[$i]}/$fileName";
                            $isDirect = true;
                        }
                    }
                }
            }
        }
        $template = (!empty($template)) ? $template : $this->params->get('default_bazar_template');
        if (strpos($template, '.html') === false && strpos($template, '.twig') === false) {
            $template = $template . '.tpl.html';
        }
        $templatePath = $isDirect ? $template : "@bazar/{$template}";
        $data['param']['template'] = basename($template);
        $dataContainer = new DataContainer($data);
        $this->eventDispatcher->yesWikiDispatch('groupmanagement.bazarliste.entriesready', compact(['dataContainer']));
        $data = $dataContainer->getData();
        try {
            return empty($data['fiches'])
                ? $this->render('@templates/alert-message.twig',[
                    'type' => 'info',
                    'message' => _t('BAZ_IL_Y_A').' 0 '. _t('BAZ_FICHE')
                ])
                : $this->render($templatePath, $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">'.$e->getMessage().'</div>';
        }
    }

    public function registerSubscribers()
    {
        if (!class_exists(YesWikiEventCompilerPass::class, false)) {
            $containerBuilder = $this->wiki->services;
            if ($containerBuilder && $containerBuilder instanceof ContainerBuilder) {
                if ($containerBuilder->has(CommentService::class)) {
                    $commentService = $containerBuilder->get(CommentService::class);
                    if (method_exists($this->eventDispatcher, 'hasListeners') && !$this->eventDispatcher->hasListeners()) {
                        $commentService->registerSubscribers();
                    }
                }
            }
        }
    }

    /**
     * get options from a tag
     * @param string $tag
     * @param array &$options
     * @param array &$associatedFields
     * @return bool success
     */
    public function getOptions(string $tag, array &$options, array &$associatedFields): bool
    {
        if (empty($tag)) {
            return false;
        }
        $rawOptions = $this->tripleStore->getOne($tag, self::GROUP_TRIPLE_PROPERTY, "", "");
        if (empty($rawOptions)) {
            return false;
        }
        $rawOptions = json_decode($rawOptions, true);
        if (!is_array($rawOptions)) {
            return false;
        }
        $options = $rawOptions;

        $associatedFields = $this->findAssociatedFields($options,$options['fieldNames'] ?? "");

        return !empty($options['parentsForm']) &&
            !empty($options['childrenForm']) &&
            !empty($options['groupSuffix']) &&
            strlen($options['groupSuffix']) > 3 &&
            isset($options['allowedToWrite']) &&
            !empty($associatedFields) ;
    }

    private function findAssociatedFields(array $options,string $fieldNames = "", string $newChildformId = "", string $newParentFormId = ""): array
    {
        if (empty($newChildformId) && empty($options['childrenForm'])) {
            return [];
        }
        if (empty($newChildformId)) {
            $newChildformId = $options['childrenForm'];
        }
        $fields = [];
        $newparents = empty($newParentFormId) ? [] : [$newParentFormId];
        if (!empty($fieldNames)) {
            foreach (explode(',', $fieldNames) as $newFieldName) {
                $newField = $this->formManager->findFieldFromNameOrPropertyName($newFieldName, $newChildformId);
                if (!empty($newField) && $this->isRightField($options,$newField, $newparents) && empty(array_filter($fields, function ($field) use ($newField) {
                    return $field->getPropertyName() == $newField->getPropertyName();
                }))) {
                    $fields[] = $newField;
                }
            }
        }
        if (empty($fields)) {
            $form = $this->formManager->getOne($newChildformId);
            if (empty($form)) {
                return [];
            } else {
                foreach ($form['prepared'] as $field) {
                    if ($this->isRightField($options,$field, $newparents)) {
                        $fields[] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    public function setOptions(array $options, string $tag): bool
    {
        if (empty($tag)) {
            return false;
        }
        if (empty($options) || !is_array($options)) {
            return false;
        }
        $options['allowedToWrite'] = isset($options['allowedToWrite']) && (
            $options['allowedToWrite'] === true ||
            (
                is_array($options['allowedToWrite']) && isset($options['allowedToWrite']['allowedToWrite'])
                && in_array($options['allowedToWrite']['allowedToWrite'], [1,"1",true,"true"])
            )
        );
        $options['fieldNames'] = empty($options['fieldNames'])
            ? ""
            : (
                is_string($options['fieldNames'])
                ? $options['fieldNames']
                : (
                    is_array($options['fieldNames'])
                    ? implode(',', array_keys(array_filter($options['fieldNames'], function ($value, $key) {
                        return $key != "fromForm" && in_array($value, [1,"1",true,"true"], true);
                    }, ARRAY_FILTER_USE_BOTH)))
                    : ""
                )
            );
        $options = filter_var_array($options, [
            'parentsForm' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'childrenForm' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'fieldNames' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'groupSuffix' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'allowedToWrite' => FILTER_VALIDATE_BOOL,
            'mainGroup' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        ]);
        $fields = $this->findAssociatedFields($options,!empty($options['fieldNames']) ? $options['fieldNames'] : "", $options['childrenForm'], $options['parentsForm']);
        $options['fieldNames'] = empty($fields) ? "" : implode(',', array_map(function ($field) {
            return $field->getPropertyName();
        }, $fields));

        $previousItems = $this->tripleStore->getAll($tag, self::GROUP_TRIPLE_PROPERTY, "", "");
        if (!empty($previousItems)) {
            for ($i=1; $i < count($previousItems); $i++) {
                $this->tripleStore->delete($previousItems[$i]['resource'], self::GROUP_TRIPLE_PROPERTY, $previousItems[$i]['value'], "", "");
            }
            return in_array($this->tripleStore->update($previousItems[0]['resource'], self::GROUP_TRIPLE_PROPERTY, $previousItems[0]['value'], json_encode($options), "", ""), [0,3]);
        } else {
            return $this->tripleStore->create($tag, self::GROUP_TRIPLE_PROPERTY, json_encode($options), "", "") == 0;
        }
    }

    public function isRightField(array $options,BazarField $field, array $rightIds = []): bool
    {
        return ($field instanceof SelectEntryField || $field instanceof RadioEntryField || $field instanceof CheckboxEntryField) &&
            (empty($rightIds) ? $field->getLinkedObjectName() == $options['parentsForm'] : in_array($field->getLinkedObjectName(), $rightIds));
    }

    public function saveGroup(string $selectedEntry, array $newData, array $options)
    {
        $groupName = "{$selectedEntry}{$options['groupSuffix']}";
        $this->updateGroupAcl($selectedEntry, $groupName, $newData, $options);
        $this->addGroupToMainGroup($groupName, $options);
        $this->updateWriteAcl($selectedEntry, $groupName, $options);
        $this->updateReadAcl($selectedEntry, $groupName);
    }

    private function updateGroupAcl(string $selectedEntry, string $groupName, array $newData, array $options)
    {
        $selectedUsers = [];
        foreach ($newData as $key => $value) {
            if ($key != 'fromForm' && in_array($value, [1,"1",true,"true"])) {
                $userAccount = $this->userManager->getOneByName($key);
                if (!empty($userAccount)) {
                    $selectedUsers[] = $key;
                }
            }
        }
        $groupAcl = $this->wiki->GetGroupACL($groupName);
        if (empty($groupAcl)) {
            $groupAcl = "";
        }
        $accountsCurrentlyInGroup = $this->getAccountsInGroupForSelectedEntry($selectedEntry, $options);
        foreach ($selectedUsers as $userName) {
            if (!in_array($userName, $accountsCurrentlyInGroup)) {
                if (!empty($groupAcl) && substr($groupAcl, -strlen("\n")) != "\n") {
                    $groupAcl .= "\n";
                }
                $groupAcl .= "$userName\n";
            }
        }
        foreach ($accountsCurrentlyInGroup as $userName) {
            if (!in_array($userName, $selectedUsers)) {
                $groupAcl = preg_replace("/(?<=^|\\r|\\n)$userName(?:\\r|\\n)+/", "", $groupAcl);
            }
        }
        if (empty(trim($groupAcl))) {
            $groupAcl = "@admins\n";
        } else {
            $groupAcl = str_replace(["\r\r","\n\n"], ["\r","\n"], $groupAcl);
        }
        $this->wiki->SetGroupACL($groupName, $groupAcl);
    }

    public function getAccountsInGroupForSelectedEntry(string $selectedEntry, array $options): array
    {
        $groupAcl = $this->wiki->GetGroupACL("{$selectedEntry}{$options['groupSuffix']}");
        if (empty($groupAcl)) {
            return [];
        }

        $groupmembers = array_filter(array_map('trim', explode("\n", $groupAcl)), function ($line) {
            switch ($line) {
                case '*':
                case '!*':
                case '+':
                case '!+':
                case '%':
                case '!%':
                    return false;
                default:
                    if (in_array(substr($line, 0, 1), ["@","#",'!'])) {
                        return false;
                    }
                    return !empty($line) && !empty($this->userManager->getOneByName($line));
            }
        });

        return array_values($groupmembers);
    }

    private function addGroupToMainGroup(string $groupName, array $options)
    {
        if (!empty($options['mainGroup'])) {
            $mainGroupAcl = $this->wiki->GetGroupACL($options['mainGroup']);
            if (empty($mainGroupAcl)) {
                $mainGroupAcl = "";
            }
            $isAlreadyDefined = preg_match("/^@$groupName$/m", $mainGroupAcl);
            if (!$isAlreadyDefined) {
                $mainGroupAcl .= ((substr($mainGroupAcl, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
                $this->wiki->SetGroupACL($options['mainGroup'], $mainGroupAcl);
            }
        }
    }

    private function updateWriteAcl(string $selectedEntry, string $groupName, array $options)
    {
        $currentWrite = $this->aclService->load($selectedEntry, 'write', false);
        $currentWrite = empty($currentWrite['list']) ? "" : $currentWrite['list'];
        $isAlreadyDefined = preg_match("/^@$groupName$/m", $currentWrite);
        if ($options['allowedToWrite'] && !$isAlreadyDefined) {
            $newCurrentWrite = $currentWrite .((substr($currentWrite, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
        } elseif (!$options['allowedToWrite'] && $isAlreadyDefined) {
            $newCurrentWrite = (trim($currentWrite) == "@$groupName")
                ? "@admins\n"
                : preg_replace("/(?<=^|\\r|\\n)@$groupName(?:\\r|\\n)+/", "", $currentWrite);
        }
        if (isset($newCurrentWrite)) {
            $this->aclService->save($selectedEntry, 'write', $newCurrentWrite);
        }
    }

    private function updateReadAcl(string $selectedEntry, string $groupName)
    {
        $currentRead = $this->aclService->load($selectedEntry, 'read', true);
        $currentRead = empty($currentRead['list']) ? "" : $currentRead['list'];
        $isAlreadyDefined = preg_match("/^@$groupName$/m", $currentRead);
        if (!$isAlreadyDefined) {
            $currentRead .= ((substr($currentRead, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
            $this->aclService->save($selectedEntry, 'read', $currentRead);
        }
    }
}
