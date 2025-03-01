import { shallowMount } from '@vue/test-utils';
import swCustomerDetailBase from 'src/module/sw-customer/view/sw-customer-detail-base';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

Shopware.Component.register('sw-customer-detail-base', swCustomerDetailBase);

const customFields = [
    {
        customFields: [
            {
                config: {
                    customFieldPosition: 1
                },
                id: '5af5c4492a204b17a7e800d94425fe0c'
            },
            {
                config: {
                    customFieldPosition: 4
                },
                id: 'de8de156da134dabac24257f81ff282f'
            },
            {
                config: {
                    customFieldPosition: 6
                },
                id: 'e33027523c86413c8018f75de49be56f'
            },
            {
                config: {
                    customFieldPosition: 9
                },
                id: 'f95226379abf48ceb3129de7f266d293'
            },
            {
                config: {
                    customFieldPosition: 22
                },
                id: '8bc279512c6e4f40afe410264b266c12'
            },
            {
                config: {
                    customFieldPosition: 45
                },
                id: '3497634a5336477597586e9618c0ca4f'
            }
        ]
    }
];


async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-customer-detail-base'), {
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(customFields),
                        get: () => Promise.resolve({ id: '' })
                    };
                },
            }

        },

        propsData: {
            customerEditMode: false,
            customer: {}
        },

        stubs: {
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-customer-card': {
                template: '<div></div>'
            },
            'sw-custom-field-set-renderer': await Shopware.Component.build('sw-custom-field-set-renderer'),
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': {
                template: '<div></div>'
            },
            'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
        }
    });
}

describe('module/sw-customer/view/sw-customer-detail-base.spec.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort custom fields by their position', async () => {
        const formFields = wrapper.findAll('.sw-form-field-renderer');

        expect(formFields.length).toBe(6);

        const [first, second, third, fourth, fifth, sixth] = formFields.wrappers;

        expect(first.attributes('customfieldposition')).toBe('1');
        expect(second.attributes('customfieldposition')).toBe('4');
        expect(third.attributes('customfieldposition')).toBe('6');
        expect(fourth.attributes('customfieldposition')).toBe('9');
        expect(fifth.attributes('customfieldposition')).toBe('22');
        expect(sixth.attributes('customfieldposition')).toBe('45');
    });
});
