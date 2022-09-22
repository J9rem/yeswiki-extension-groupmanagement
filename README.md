# yeswiki-extension-groupmanagement

 - [English](#english)
 - [Français](#français)

## English

Extension [YesWiki](https://yeswiki.net/) to make easier the management of admins of entries.

### Authors

 - Jérémy Dufraisse
 - [Habitat Participatif France](https://www.habitatparticipatif-france.fr/)
 - and all contributors indicated on this page : <https://github.com/J9rem/yeswiki-extension-groupmanagement/graphs/contributors>

### Install

In page `GererMisesAJour` on your YesWiki website, search extension `groupmanagement` and install it.
You may have to install the extension [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem) by copiyng the folder `alternativeupdatej9rem` from [this archive](https://repository.oui-wiki.pro/doryphore/extension-alternativeupdatej9rem-latest.zip) into the folder `tools` on your website.

#### Use Cases

This extension allows you to transfer the right to certain accounts to define groups, without being an administrator of the site.

 - Accounts that can obtain this right must own a record in a well-identified form. 
 - These accounts can then add other members in the group of administrators of this card and/or in the group of people authorized to see this card.

#### Configuration

 1. create, as an administrator, a new page
 2. use the `components` button, to add the rights management action `{{groupmanagement}}`
 3. Save
 4. Choose the form for which the option should be activated (e.g. a `structures` form) and note its number
 5. Go to the form containing the members' cards and add, if this is not already the case, a field of type `list`, `radio` or `checkbox` which points to the form `structure`
 6. It is often desirable that the logged-in person has an account linked to the `member` card. This can be guaranteed by the `utilisateur_wikini` field to be placed in the `member` form
 7. return to the previously created page, as administrator
 8. click on the toothed wheel of the action (not the one on the website)
 9. choose the `structure` form first, the `member` form second and tick the `bazar` fields that need to be scanned
 10. Check the box to give write rights if this is the desired behavior.
 11. A suffix will be added to each `structures` card name to give the name of the associated group. This suffix is customizable.
 12. It is possible to configure a macro group where each administrator of a structure will be added.
 13. Save and then return
 14. You can now choose a structure and allow potential members to be amdinstrators.

The newly created groups can then be used anywhere to set finer read or edit rights.

#### Tips

 - bazar field `autoregisterlinkedentryatcreation` aims to be added in the `structure` form so that the connected account is automatically linked to this `structure` when it is created. This can only work if the logged-in user already has a `member` card associated with their account.
 - bazar field `aclselect` is a new field provided by this extension that allows you to define whether you want your card to be visible or not. this field is still under construction.

### Warranty

Like written in the licence file, there is no warranty on usage of this software. Refer to licence file for details.
Developpers of this extension can not be responsible of consequences of the usage of this extension.

----

## Français

Extension [YesWiki](https://yeswiki.net/) pour mettre faciliter la gestion des droits des fiches.

### Auteurs

 - Jérémy Dufraisse
 - [Habitat Participatif France](https://www.habitatparticipatif-france.fr/)
 - et tous les contributeurs et toutes les contributrices indiqués sur cette page : <https://github.com/J9rem/yeswiki-extension-groupmanagement/graphs/contributors>

### Installation

Dans la page `GererMisesAJour` de votre YesWiki, recherchez l'extension `groupmanagement` et installez-là.
Vous pourriez avoir besoin d'installer l'extension [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem) en copiant le dossier `alternativeupdatej9rem` depuis [l'archive](https://repository.oui-wiki.pro/doryphore/extension-alternativeupdatej9rem-latest.zip) dans le dossier `tools` sur votre site.

### Utilisation

#### Cas d'usage

Cet extension permet de transférer le droit à certains comptes de définir des groupes, sans être administrateur du site.

 - Les comptes qui peuvent obtenir ce droit doivent être propriétaire d'une fiche dans un formulaire bien identifié.
 - Ces comptes peuvent alors ajouter d'autres membres dans le groupe des administrateurs de cette fiche et/ou dans le groupe des personnes autorisées à voir cette fiche.

#### Configuration

 1. créer, en tant qu'administrateur, une nouvelle page
 2. utiliser le bouton `composants`, pour ajouter l'action de gestion des droits `{{groupmanagement}}`
 3. sauvegarder
 4. Choisir le formulaire pour lequel il faut activer l'option (par exemple un formulaire `structures`) et noter son numéro
 5. Se rendre dans le formulaire contenant les fiches des membres et ajouter, si ça n'est pas déjà le cas, un champ de type `liste`, `radio` ou `checkbox` qui pointe vers le formulaire `structure`
 6. Il est souvent souhaitable que la personne connecté ait un compte lié à la fiche `membre`. Ceci peut être garanti grâce au champ `utilisateur_wikini` à placer dans le formulaire `membre`
 7. revenir dans la page précédemment créée, en tant qu'administrateur
 8. cliquer sur la roue crantée de l'action (pas celle du site internet)
 9. choisir le formulaire `structure` en premier, le formulaire `membre` en second et cocher les champs `bazar` qu'il faut scanner
 10. cocher la case pour donner les droits d'écriture si c'est le comportement souhaité.
 11. un suffixe sera ajouté à chaque nom de fiche `structures` pour donner le nom du groupe associé. Ce suffixe est personnalisable.
 12. Il est possible de configurer un macro groupe où chaque administrateur d'une structure sera ajouté.
 13. Sauvegarder puis retour
 14. Vous pouvez maintenant choisir une structure et autoriser les membres potentiels à être amdinstrateurs.

Les nouveaux groupes créés peuvent alors être utilisés partout pour définir des droits de lecture ou de modification plus fins.

#### Astuces

 - champ bazar `autoregisterlinkedentryatcreation` a pour objectif d'être ajouté dans le formulaire `structure` afin que le compte connecté soit automatiquement relié à cette `structure` lors de la création de celle-ci. Ceci ne peut fonctionner que si l'utilisateur connecté possède déjà une fiche `membre` associée à son compte.
 - champ bazar `aclselect` est un nouveau champ fournit par cette extension qui permet de définir si on veut que sa fiche soit visible ou non. ce champ est encore en phase de construction.

### Garantie

Comme énoncé dans le fichier de licence, il n'y a pas de garantie sur l'usage de ce logiciel. Référer au fichier de licence pour les détails.
Les développeurs de cette extension ne peuvent être responsables des conséquences qui découlent de l'usage de cette extension.