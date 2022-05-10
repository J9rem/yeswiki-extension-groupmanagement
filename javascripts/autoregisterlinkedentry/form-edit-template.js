typeUserAttrs = {
  ...typeUserAttrs,
  ...{
      "autoregisterlinkedentry": {
        listeOrFormId: {
          label: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM'),
          options: {
            ...formAndListIds.forms,
          },
        },
        formId: {
          label: "",
          options: formAndListIds.forms,
        },
        linkedFieldName: {
          label: "Nom du champ lié",
          value: ""
        },
        defaultValue: {
          label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
          options: {
            "oui": _t('YES'),
            "no": _t('NO'),
          },
        },
      }
    }
};

templates = {
  ...templates,
  ...{
    autoregisterlinkedentry: function (field) {
      return {
        field: '<span></span><input type="checkbox" checked/> Oui',
        onRender: function(){
          templateHelper.defineLabelHintForGroup(field,'listeOrFormId','Choisir le formulaire des fiches filles');
          
          templateHelper.prependHTMLBeforeGroup(field,'linkedFieldName',$('<div/>').addClass('form-group')
            .append('Donner le nom du champ concerné dans ce formulaire.')
            .append('<br/>')
            .append('Si vide, tous les champs radio/liste/checkboxfiche pointant vers le présent formualire seront mis à jour.'));
        },
      };
    },
  }
};

yesWikiMapping = {
  ...yesWikiMapping,
  ...{
    "autoregisterlinkedentry": {
      ...lists,
      ...{
        6: "linkedFieldName",
        7: "",
        8: "",
        9: "",
      }
    }
  }
};

typeUserDisabledAttrs['autoregisterlinkedentry'] = ['required','name','value'];

fields.push({
    label: 'Être enregistré·e comme membre de cette structure lors de la création de fiche ?',
    name: "autoregisterlinkedentry",
    attrs: { type: "autoregisterlinkedentry" },
    icon: '<i class="fas fa-external-link-alt"></i>',
  });
