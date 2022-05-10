<?php

return [

    
    // tools/groupmanagement/actions/GroupManagementAction.php
    'GRPMNGT_ACTION_NO_USER' => 'L\'action \'{{groupmanagement}}\' est réservée aux personnes connectées.',
    'GRPMNGT_ACTION_NO_OPTIONS' => 'Les options de l\'action \'{{groupmanagement}}\' ne sont pas correctement paramétrées.',

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
];
