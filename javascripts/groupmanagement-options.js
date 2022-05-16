document.addEventListener('alpine:init', () => {
    Alpine.data('optionsData', () => ({
        child: '',
        parent: '',
        childrenForms: {},
        parentsForms: {},
        selectedFields: {},
        oldValueSelectedFields: "",
        updateSelected(elem,type) {
            switch (type) {
                case 'child':
                    this.child = $(elem).val();
                    this.initSelectedFieldsLater();
                    break;
                case 'parent':
                    this.parent = $(elem).val();
                    break;
                default:
                    break;
            }
        },
        get childForm(){
            return this.child;
        },
        get parentForm(){
            return this.parent;
        },
        get availableFields(){
            if (this.child.length > 0 && this.childrenForms[this.child] != undefined){
                let childForm = this.childrenForms[this.child];
                let selectedParent = this.parent;
                let result = [];
                if (selectedParent.length > 0){
                    for (const key in childForm.fields) {
                        if (childForm.fields[key].linkedObjectName == selectedParent){
                            result.push(childForm.fields[key]);
                        }
                    }
                }
                return result;
            }
            return [];
        },
        checkboxCheck(field){
            if (this.child.length > 0 && this.selectedFields[this.child] != undefined){
                let fieldsPropertyNames = this.selectedFields[this.child];
                if (fieldsPropertyNames.length > 0 && fieldsPropertyNames.includes(field.propertyname)){
                    return {
                        checked: 'checked',
                        ['data-checked']: 1
                    };
                }
            }
            return {};  
        },
        initSelectedFields(oldValue){
            this.oldValueSelectedFields = oldValue;
        },
        initSelectedFieldsLater(){
            let oldValue = this.oldValueSelectedFields;
            let fields = this.availableFields;
            let oldValues = oldValue.split(',');
            if (Object.keys(fields).length > 0){
                let ids = [];
                for (const key in fields) {
                    let currentPropertyName = fields[key].propertyname;
                    let currentName = fields[key].name;
                    if ((oldValues.includes(currentPropertyName) || oldValues.includes(currentName)) && !ids.includes(currentPropertyName)){
                        ids.push(currentPropertyName);
                    }
                }
                this.selectedFields[this.child] = ids;
            }
        },
        saveCheckboxValue(elem,fieldName){
            if (this.child.length > 0){
                if (this.selectedFields[this.child] == undefined){
                    this.selectedFields[this.child] = [];
                }
                if (!$(elem).prop('checked') && this.selectedFields[this.child].includes(fieldName)) {
                    this.selectedFields[this.child] = this.selectedFields[this.child].filter(selectedFieldName => fieldName != selectedFieldName);
                } else if ($(elem).prop('checked') && !this.selectedFields[this.child].includes(fieldName)){
                    this.selectedFields[this.child].push(fieldName);
                }
            }
        },
        toogleCheckbox(elem,cssAnchor){
            let checkAll = $(elem).prop('checked');
            $(cssAnchor).find('.element_checkbox').each(function(){
                if ((checkAll && !$(this).prop('checked')) || (!checkAll && $(this).prop('checked'))){
                    $(this).click();
                }
            });
        },
        get availableChildrenForms(){
            if (this.parent.length == 0 && this.parentsForms[this.parent] == undefined){
                return this.childrenForms;
            } else {
                let childrenFormsIds = this.parentsForms[this.parent].availableChildren;
                let result = {}
                for (const key in this.childrenForms) {
                    let childForm = this.childrenForms[key];
                    if (childrenFormsIds.includes(childForm.id)) {
                        result[childForm.id] = childForm;
                    }
                }
                return result;
            }
        },
        get firstKeyAvailableChildrenForms(){
            let keys = Object.keys(this.availableChildrenForms)
            return keys.length == 0 ? "" : keys[0];
        },
        getSelected(index,value,elem){
            let useValue = this.parent.length > 0 &&
                this.parentsForms[this.parent] != undefined &&
                this.parentsForms[this.parent].availableChildren.includes(value)
            ;
            if ((useValue && value.length > 0 && index == value) || ((!useValue || value.length == 0) && this.firstKeyAvailableChildrenForms == index)){
                this.$nextTick(() => {
                    this.updateSelected(elem,'child')
                    $(elem).change();
                });
                return {
                    selected:'selected'
                };
            }
            return {

            };
        }
    }))
})