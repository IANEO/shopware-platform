import { shallowMount } from '@vue/test-utils';
import swOrderDocumentSettingsModal from 'src/module/sw-order/component/sw-order-document-settings-modal';
import swOrderDocumentSettingsCreditNoteModal from 'src/module/sw-order/component/sw-order-document-settings-credit-note-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-group';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';

Shopware.Component.register('sw-order-document-settings-modal', swOrderDocumentSettingsModal);
Shopware.Component.extend('sw-order-document-settings-credit-note-modal', 'sw-order-document-settings-modal', swOrderDocumentSettingsCreditNoteModal);

const orderFixture = {
    id: 'order1',
    documents: [
        {
            id: '1',
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            },
            config: {
                documentNumber: 1000,
                custom: {
                    invoiceNumber: 1000,
                }
            }
        },
        {
            id: '2',
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            },
            config: {
                documentNumber: 1001,
                custom: {
                    invoiceNumber: 1001,
                }
            }
        },
        {
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            },
            config: {
                documentNumber: 1001,
                custom: {
                    invoiceNumber: 1001,
                }
            }
        },
        {
            id: '3',
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '2',
                name: 'Delivery note',
                technicalName: 'delivery_note',
            },
            config: {
                documentNumber: 1001,
                custom: {
                    deliveryNoteNumber: 1001,
                }
            }
        }
    ],
    currency: {
        shortName: 'EUR',
    },
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: [
        {
            id: '3',
            type: 'credit',
            label: 'Credit item',
            quantity: 1,
            payload: [],
            price: { quantity: 1,
                totalPrice: -100,
                unitPrice: -100,
                calculatedTaxes: [
                    {
                        price: -100,
                        tax: -10,
                        taxRate: 10
                    }
                ],
                taxRules: [
                    {
                        taxRate: 10,
                        percentage: 100
                    }
                ] }
        }]
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-document-settings-credit-note-modal'), {
        stubs: {
            'sw-order-document-settings-modal': await Shopware.Component.build('sw-order-document-settings-modal'),
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': true,
            'sw-datepicker': true,
            'sw-checkbox-field': true,
            'sw-switch-field': true,
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-group': await Shopware.Component.build('sw-button-group'),
            'sw-context-menu-item': true,
            'sw-upload-listener': true,
            'sw-textarea-field': true,
            'sw-icon': true,
            'sw-select-field': await Shopware.Component.build('sw-select-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-loader': true,
            'sw-description-list': {
                template: '<div class="sw-description-list"><slot></slot></div>'
            },
        },
        provide: {
            numberRangeService: {
                reserve: () => Promise.resolve({ number: 1337 }),
            },

            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve({
                        id: '1',
                        deepLinkCode: 'b829671a-20a3-4f81-be1d-b5df2c6dcd12',
                        lineItems: [{
                            id: '3',
                            type: 'credit',
                            label: 'Credit item',
                        }]
                    }),
                })
            }
        },
        propsData: {
            order: orderFixture,
            currentDocumentType: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
        },
    });
}

describe('sw-order-document-settings-credit-note-modal', () => {
    let wrapper;

    beforeEach(async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should compute highlightedItems correctly', async () => {
        await wrapper.setData({
            lineItems: [
                {
                    type: 'product',
                    id: 'INVOICE_ITEM'
                },
                {
                    type: 'custom',
                    id: 'CUSTOM_ITEM'
                },
                {
                    type: 'credit',
                    id: 'CREDIT_1'
                },
                {
                    type: 'credit',
                    id: 'CREDIT_2'
                }
            ]
        });

        expect(wrapper.vm.highlightedItems).toStrictEqual([{
            type: 'credit',
            id: 'CREDIT_1'
        }, {
            type: 'credit',
            id: 'CREDIT_2'
        }]);
    });

    it('should compute documentPreconditionsFulfilled correctly', async () => {
        expect(wrapper.vm.documentPreconditionsFulfilled).toBe(false);

        await wrapper.setData({
            documentConfig: {
                custom: {
                    invoiceNumber: 'INVOICE_NUM'
                }
            },
            lineItems: [
                {
                    type: 'credit',
                    id: 'CREDIT_1'
                },
                {
                    type: 'credit',
                    id: 'CREDIT_2'
                },
            ],
        });

        expect(wrapper.vm.documentPreconditionsFulfilled).toEqual('INVOICE_NUM');
    });

    it('should render invoiceNumbers correctly', async () => {
        await wrapper.setProps({
            order: {
                currency: {
                    shortName: 'USD'
                },
                lineItems: [],
                documents: [
                    {
                        config: {
                            custom: {
                                invoiceNumber: 'INVOICE_003'
                            }
                        },
                        documentType: {
                            technicalName: 'invoice'
                        },
                        id: 'DOCUMENT_1'
                    },
                    {
                        config: {
                            custom: {
                                invoiceNumber: null
                            }
                        },
                        documentType: {
                            technicalName: 'credit'
                        },
                        id: 'DOCUMENT_2'
                    },
                    {
                        config: {
                            custom: {
                                invoiceNumber: 'INVOICE_001'
                            }
                        },
                        documentType: {
                            technicalName: 'invoice'
                        },
                        id: 'DOCUMENT_3'
                    },
                    {
                        config: {
                            custom: {
                                invoiceNumber: 'INVOICE_002'
                            }
                        },
                        documentType: {
                            technicalName: 'invoice'
                        },
                        id: 'DOCUMENT_4'
                    },
                    {
                        config: {},
                        documentType: {
                            technicalName: 'storno'
                        },
                        id: 'DOCUMENT_5'
                    },
                ],
            },
        });

        await wrapper.vm.createdComponent();

        // Filtered and sorted
        expect(wrapper.vm.invoiceNumbers).toEqual(['INVOICE_001', 'INVOICE_002', 'INVOICE_003']);
    });

    it('should emit loading-document onCreateDocument', async () => {
        await wrapper.setProps({
            order: {
                currency: {
                    shortName: 'USD'
                },
                lineItems: [],
                documents: []
            },
        });

        await wrapper.vm.onCreateDocument();

        // Filtered and sorted
        expect(wrapper.emitted()['loading-document']).toBeTruthy();
    });

    it('should call numberRangeService.reserve if documentNumberPreview equal documentConfig.documentNumber', async () => {
        const number = 'RESERVE_NUMBER';
        const spyReserve = jest.spyOn(wrapper.vm.numberRangeService, 'reserve').mockImplementation(() => Promise.resolve({
            number
        }));

        await wrapper.setProps({
            order: {
                salesChannelId: 'Headless',
                currency: {
                    shortName: 'USD'
                },
                lineItems: [],
                documents: []
            },
        });

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'credit_note',
            },
            documentNumberPreview: 'PREVIEW_NUM_001',
            documentConfig: {
                documentNumber: 'PREVIEW_NUM_001',
            },
        });

        wrapper.vm.createNotificationInfo = jest.fn();

        await wrapper.vm.onCreateDocument();

        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.info.DOCUMENT__NUMBER_WAS_CHANGED'
        });
        expect(spyReserve).toHaveBeenCalledTimes(1);
        expect(spyReserve).toBeCalledWith('document_credit_note', 'Headless', false);
        expect(wrapper.vm.documentConfig.custom.creditNoteNumber).toEqual(number);
        expect(wrapper.vm.documentConfig.documentNumber).toEqual(number);
        expect(wrapper.emitted()['document-create']).toBeTruthy();
    });

    it('should set document creditNoteNumber if documentNumberPreview not equal config documentNumber', async () => {
        await wrapper.setData({
            documentNumberPreview: 'PREVIEW_NUM_001',
            documentConfig: {
                documentNumber: 'PREVIEW_NUM_002',
            },
        });

        await wrapper.vm.onCreateDocument();

        expect(wrapper.vm.documentConfig.custom.creditNoteNumber).toEqual('PREVIEW_NUM_002');
        expect(wrapper.emitted()['document-create']).toBeTruthy();
    });

    it('should show only invoice numbers in invoice number select field', async () => {
        const invoiceSelect = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select')
            .findAll('option');

        expect(invoiceOptions.at(1).text()).toEqual('1000');
        expect(invoiceOptions.at(2).text()).toEqual('1001');
    });

    it('should disable create button if there is no selected invoice', async () => {
        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBe('disabled');

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBe('disabled');
    });

    it('should enable create button if there is at least one selected invoice', async () => {
        const invoiceSelect = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select')
            .findAll('option');

        await invoiceOptions.at(1).setSelected();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBeUndefined();

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBeUndefined();
    });

    it('should set deepLinkCode by version context if the selected invoice', async () => {
        const invoiceSelect = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.sw-order-document-settings-credit-note-modal__invoice-select')
            .findAll('option');

        await invoiceOptions.at(3).setSelected();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfig.custom.invoiceNumber).toEqual('');
        expect(wrapper.vm.deepLinkCode).toBeNull();
        expect(wrapper.vm.lineItems).toEqual([]);

        await invoiceOptions.at(1).setSelected();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.documentConfig.custom.invoiceNumber).toEqual(1000);
        expect(wrapper.vm.deepLinkCode).toEqual('b829671a-20a3-4f81-be1d-b5df2c6dcd12');
        expect(wrapper.vm.lineItems).toEqual([{
            id: '3',
            type: 'credit',
            label: 'Credit item',
        }]);
    });
});
