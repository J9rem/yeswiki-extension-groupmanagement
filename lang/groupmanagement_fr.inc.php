<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    // docs/actions/bazarliste.yaml
    'GRPMNGT_BAZARLISTE_PARAM_LABEL' => 'Afficher uniquement les fiches que l\'utilisateur peut modifier',
    
    // tools/groupmanagement/actions/GroupManagementAction.php
    'GRPMNGT_ACTION_NO_USER' => 'L\'action \'{{groupmanagement}}\' est réservée aux personnes connectées.',
    'GRPMNGT_ACTION_NO_OPTIONS' => 'Les options de l\'action \'{{groupmanagement}}\' ne sont pas correctement paramétrées.',
    'GRPMNGT_ACTION_VALUES_SAVED' => 'Valeurs sauvegardées',

    // tools/groupmanagement/fields/AutoRegisterLinkedEntryAtCreationField.php

    'GRPMNGT_AUTOREGISTERLINKEDENTRY_ERROR_MSG' => 'Il n\'a pas été possible de mettre à jour la fiche liée car %{errorOrigin} !',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_FORM_ERROR' => 'le numéro du formulaire associé est mal saisie dans le champ',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NO_FORM' => 'le numéro du formulaire \'%{formId}\' ne correspond pas à un formulaire existant',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NO_ENTRIES' => 'aucune fiche, dont vous êtes le propriétaire, n\'a été trouvée dans le formulaire \'%{formId}\'',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NO_THIS_FIELDNAME' => 'le nom de champ recherché \'%{fieldName}\' n\'a pas été trouvé ou n\'est pas du bon type dans le formulaire \'%{formId}\'',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NO_FIELDNAMES' => 'aucun champ possible n\'a pas été trouvé dans le formulaire \'%{formId}\'',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NO_FIELDTYPE' => 'le champ \'%{fieldName}\' dans le formulaire \'%{formId}\' ne pointe pas vers le formulaire \'%{currentFormId}\'',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_OK_MSG' => 'Mise à jour réussie pour les fiches filles \'%{entries}\' !',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_OK_ONE_MSG' => 'Mise à jour réussie pour la fiche fille \'%{entry}\' !',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NOT_ALL_UPDATED_MSG' => 'Mise à jour réussie pour les fiches filles \'%{entriesOK}\' mais avec des erreurs pour les fiches \'%{entriesNotOk}\'!',
    'GRPMNGT_AUTOREGISTERLINKEDENTRY_NOT_UPDATED_MSG' => 'Mise à jour non réussie pour les fiches filles \'%{entriesNotOk}\'!',

    // tools/groupmanagement/templates/actions/groupmanagement*.twig
    'GRPMNGT_ACTION_TITLE' => 'Gestion des groupes de droits d\'accès',
    'GRPMNGT_ACTION_MANAGE_OPTIONS' => 'Gérer les options',
    'GRPMNGT_ACTION_MANAGE_OPTIONS_TITLE' => 'Gestion des options',
    'GRPMNGT_ACTION_OPTIONS_SAVED' => 'Options sauvegardées',
    'GRPMNGT_ACTION_OPTIONS_NOT_SAVED' => 'Les options ont mal été sauvegardées',
    'GRPMNGT_ACTION_PARENTSFORM_LABEL' => 'Formulaire contenant la fiche mère',
    'GRPMNGT_ACTION_PARENTSFORM_HINT' => 'Structure/entité/groupe où appartient le membre',
    'GRPMNGT_ACTION_CHILDRENFORM_LABEL' => 'Formulaire contenant la fiche fille',
    'GRPMNGT_ACTION_CHILDRENFORM_HINT' => 'Fiche membre/ressource reliée à la fiche mère',
    'GRPMNGT_ACTION_SELECTENTRYLABEL_LABEL' => 'Texte pour choisir une fiche :',
    'GRPMNGT_ACTION_SELECTENTRY_DEFAULT' => 'Choisir une structure',
    'GRPMNGT_ACTION_NOENTRY_LABEL' => 'Texte pour indiquer qu\'aucune fiche n\'a été trouvée :',
    'GRPMNGT_ACTION_NOENTRY_DEFAULT' => 'Vous n\'êtes administrateur d\'aucune structure !',
    'GRPMNGT_ACTION_FIELDNAME_LABEL' => 'Champ associé (facultatif)',
    'GRPMNGT_ACTION_GROUPSUFFIX_LABEL' => 'Suffix du groupe d\'utilisateurs',
    'GRPMNGT_ACTION_GROUPSUFFIX_DEFAULT' => 'Admins',
    'GRPMNGT_ACTION_ALLOWEDTOWRITE_LABEL' => 'Le groupe est autorisé à modifier la fiche mère',
    'GRPMNGT_ACTION_ALLOWEDTOWRITE_HINT' => 'droits d\'écriture de la fiche',
    'GRPMNGT_ACTION_WRONG_ENTRYID' => 'La fiche sélectionnée (\'%{selectedEntryId}\') n\'a pas été trouvée !',
    'GRPMNGT_ACTION_SELECTMEMBERS_LABEL' => 'Sélection des membres du groupe',
    'GRPMNGT_ACTION_DRAGNDROP_TITLE' => 'sélection des membres du groupe',
    'GRPMNGT_ACTION_NO_ENTRY_FOR_THIS_USER' => 'Il n\'y a pas de fiche associée pour cet utilisateur !',
];
