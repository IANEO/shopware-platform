import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/sw-text-editor/sw-text-editor-link-menu';

const seoDomainPrefix = '124c71d524604ccbad6042edce3ac799';

const linkDataProvider = [{
    buttonConfig: {
        value: 'http://www.domain.de/test',
        type: 'link',
    },
    value: 'http://www.domain.de/test',
    type: 'link',
    prefix: 'http://',
    selector: '.sw-text-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholder',
}, {
    buttonConfig: {
        value: 'tel:01234567890123',
        type: 'phone',
    },
    value: '01234567890123',
    type: 'phone',
    prefix: 'tel:',
    selector: '.sw-text-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderPhoneNumber',
}, {
    buttonConfig: {
        value: 'mailto:test@shopware.com',
        type: 'email',
    },
    value: 'test@shopware.com',
    type: 'email',
    prefix: 'mailto:',
    selector: '.sw-email-field',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderEmail',
}, {
    buttonConfig: {
        value: `${seoDomainPrefix}/detail/aaaaaaa524604ccbad6042edce3ac799#`,
        type: 'detail',
    },
    value: 'aaaaaaa524604ccbad6042edce3ac799',
    type: 'detail',
    prefix: `${seoDomainPrefix}/detail/`,
    selector: '.sw-entity-single-select',
    label: 'sw-text-editor-toolbar.link.linkTo',
    placeholder: 'sw-text-editor-toolbar.link.placeholderProduct',
}];


async function createWrapper(buttonConfig) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-text-editor-link-menu'), {
        localVue,
        stubs: {
            'sw-select-field': {
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value']
            },
            'sw-switch-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-switch-field" type="checkbox" :value="value" @input="$emit(\'input\', $event.target.value)" />'
            },
            'sw-email-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-email-field" :value="value" @input="$emit(\'input\', $event.target.value)" />'
            },
            'sw-text-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />'
            },
            'sw-url-field': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-url-field" type="url" :value="value" @input="$emit(\'input\', $event.target.value)">'
            },
            'sw-entity-single-select': {
                props: ['value', 'label', 'placeholder'],
                template: '<input class="sw-entity-single-select" :value="value" @input="$emit(\'input\', $event.target.value)">'
            },
            'sw-category-tree-field': {
                props: ['label', 'placeholder', 'criteria', 'categories-collection'],
                template: '<div class="sw-category-tree-field"></div>'
            },
            'sw-button': {
                props: ['disabled'],
                template: '<div class="sw-button" @click="$emit(\'click\', $event.target.value)"></div>'
            },
        },
        propsData: {
            buttonConfig: {
                title: 'test',
                icon: '',
                expanded: true,
                newTab: true,
                displayAsButton: true,
                value: '',
                type: 'link',
                tag: 'a',
                active: false,
                ...buttonConfig,
            }
        }
    });
}

const responses = global.repositoryFactoryMock.responses;
const categoryData = {
    id: 'test-id',
    name: 'category-name'
};

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: [{
            id: 'test-id',
            attributes: categoryData,
            relationships: []
        }],
        meta: {
            total: 1
        }
    }
});

describe('components/form/sw-text-editor/sw-text-editor-link-menu', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    linkDataProvider.forEach(link => {
        it(`parses ${link.type} URL's correctly`, async () => {
            const wrapper = await createWrapper(link.buttonConfig);

            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const inputField = wrapper.find(link.selector);
            expect(inputField.props()).toStrictEqual(
                expect.objectContaining({
                    value: link.value,
                    label: link.label,
                    placeholder: link.placeholder,
                })
            );

            let placeholderId = 'some-id';
            await inputField.setValue(placeholderId);

            if (link.type === 'detail') {
                placeholderId += '#';
            }

            await wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
            await wrapper.vm.$nextTick();

            const dispatchedInputEvents = wrapper.emitted('button-click');

            expect(dispatchedInputEvents[0]).toStrictEqual([
                {
                    buttonVariant: undefined,
                    displayAsButton: true,
                    newTab: true,
                    type: 'link',
                    value: link.prefix += placeholderId
                }
            ]);
        });
    });

    it('parses category links and reacts to changes correctly', async () => {
        const wrapper = await createWrapper({
            value: `${seoDomainPrefix}/navigation/aaaaaaa524604ccbad6042edce3ac799#`,
            type: 'link',
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const categoryTreeField = wrapper.find('.sw-category-tree-field');
        const props = categoryTreeField.props();

        expect(props.label).toBe('sw-text-editor-toolbar.link.linkTo');
        expect(props.placeholder).toBe('sw-text-editor-toolbar.link.placeholderCategory');

        expect(props.criteria).toStrictEqual(
            expect.objectContaining({
                limit: 25,
                page: 1,
            })
        );

        const associations = props.criteria.associations;

        expect(associations).toHaveLength(1);
        expect(associations[0].association).toBe('options');

        expect(associations[0].criteria.associations).toHaveLength(1);
        expect(associations[0].criteria.associations[0].association).toBe('group');


        expect(props.criteria.filters).toStrictEqual(expect.objectContaining(
            [{
                operator: 'OR',
                queries: [
                    { field: 'product.childCount', type: 'equals', value: 0 },
                    { field: 'product.childCount', type: 'equals', value: null }
                ],
                type: 'multi'
            }]
        ));

        expect(props.categoriesCollection.length).toBe(1);
        expect(props.categoriesCollection[0]).toEqual(categoryData);

        categoryTreeField.vm.$emit('selection-add', {
            id: 'new-selection'
        });
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
        await wrapper.vm.$nextTick();

        const dispatchedInputEvents = wrapper.emitted('button-click');

        expect(dispatchedInputEvents[0]).toStrictEqual([
            {
                buttonVariant: undefined,
                displayAsButton: true,
                newTab: true,
                type: 'link',
                value: '124c71d524604ccbad6042edce3ac799/navigation/new-selection#'
            }
        ]);

        categoryTreeField.vm.$emit('selection-remove');
        await wrapper.vm.$nextTick();

        const isDisabled = wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').props('disabled');
        expect(isDisabled).toBe(true);
    });

    it('should clear the state if the link category is changed', async () => {
        const wrapper = await createWrapper({
            value: 'http://www.domain.de/test',
            type: 'link',
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('link');

        const options = wrapper.find('select').findAll('option');
        await options.at(3).setSelected();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('email');

        const isDisabled = wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').props('disabled');
        expect(isDisabled).toBe(true);
    });

    it('should clear the linkTarget when the remove button is pressed', async () => {
        const wrapper = await createWrapper({
            value: 'http://www.domain.de/test',
            type: 'link',
        });

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.linkCategory).toBe('link');
        expect(wrapper.vm.linkTarget).toBe('http://www.domain.de/test');


        wrapper.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-remove').vm.$emit('click');
        await wrapper.vm.$nextTick();


        const dispatchedInputEvents = wrapper.emitted('button-click');

        expect(dispatchedInputEvents[0]).toStrictEqual([
            {
                type: 'linkRemove',
            }
        ]);
    });
});
