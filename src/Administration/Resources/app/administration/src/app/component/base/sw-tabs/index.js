import template from './sw-tabs.html.twig';
import './sw-tabs.scss';

const { Component, Feature } = Shopware;
const util = Shopware.Utils;
const dom = Shopware.Utils.dom;

/**
 * @public
 * @description Renders tabs. Each item references a route or emits a custom event.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-tabs>
 *     <sw-tabs-item>
 *         Explore
 *     </sw-tabs-item>
 *     <sw-tabs-item>
 *         My Plugins
 *     </sw-tabs-item>
 * </sw-tabs>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-tabs', {
    template,

    extensionApiDevtoolInformation: {
        property: 'ui.tabs',
        positionId: (currentComponent) => currentComponent.positionIdentifier,
    },

    props: {
        positionIdentifier: {
            type: String,
            // eslint-disable-next-line no-unneeded-ternary
            required: Feature.isActive('FEATURE_NEXT_18129') ? true : false,
            default: null,
        },

        isVertical: {
            type: Boolean,
            required: false,
            default: false,
        },

        small: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        alignRight: {
            type: Boolean,
            required: false,
            default: false,
        },

        defaultItem: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            active: this.defaultItem || '',
            isScrollable: false,
            activeItem: null,
            scrollLeftPossible: false,
            scrollRightPossible: true,
            firstScroll: false,
            scrollbarOffset: '',
            hasRoutes: false,
        };
    },

    computed: {
        tabClasses() {
            return {
                'sw-tabs--vertical': this.isVertical,
                'sw-tabs--small': this.small,
                'sw-tabs--scrollable': this.isScrollable,
                'sw-tabs--align-right': this.alignRight,
                'sw-tabs--scrollbar-active': this.scrollbarOffset > 0,
            };
        },

        arrowClassesLeft() {
            return {
                'sw-tabs__arrow--disabled': !this.scrollLeftPossible,
            };
        },

        arrowClassesRight() {
            return {
                'sw-tabs__arrow--disabled': !this.scrollRightPossible,
            };
        },

        sliderLength() {
            if (this.$children[this.activeItem]) {
                const activeChildren = this.$children[this.activeItem];
                return this.isVertical ? activeChildren.$el.offsetHeight : activeChildren.$el.offsetWidth;
            }
            return 0;
        },

        activeTabHasErrors() {
            return this.$children[this.activeItem] && this.$children[this.activeItem].hasError;
        },

        sliderClasses() {
            return { 'has--error': this.activeTabHasErrors };
        },

        sliderMovement() {
            if (this.$children[this.activeItem]) {
                const activeChildren = this.$children[this.activeItem];
                return this.isVertical ? activeChildren.$el.offsetTop : activeChildren.$el.offsetLeft;
            }
            return 0;
        },

        sliderStyle() {
            if (this.isVertical) {
                return `
                    transform: translate(0, ${this.sliderMovement}px) rotate(${this.alignRight ? '-90deg' : '90deg'});
                    width: ${this.sliderLength}px;
                `;
            }

            return `
                transform: translate(${this.sliderMovement}px, 0) rotate(0deg);
                width: ${this.sliderLength}px;
                bottom: ${this.scrollbarOffset}px;
            `;
        },

        tabContentStyle() {
            return {
                'padding-bottom': `${this.scrollbarOffset}px`,
            };
        },

        tabExtensions() {
            return Shopware.State.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },
    },

    watch: {
        '$route'() {
            this.updateActiveItem();
        },

        activeTabHasErrors() {
            this.recalculateSlider();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.updateActiveItem();
        },

        mountedComponent() {
            const tabContent = this.$refs.swTabContent;

            tabContent.addEventListener('scroll', util.throttle(() => {
                const rightEnd = tabContent.scrollWidth - tabContent.offsetWidth;
                const leftDistance = tabContent.scrollLeft;

                this.scrollRightPossible = !(rightEnd - leftDistance < 5);
                this.scrollLeftPossible = !(leftDistance < 5);
            }, 100));

            this.checkIfNeedScroll();
            this.addScrollbarOffset();

            const that = this;
            this.$device.onResize({
                listener() {
                    that.checkIfNeedScroll();
                    that.addScrollbarOffset();
                },
                component: this,
            });
            this.recalculateSlider();

            // check if tab bar contains items with url routes
            if (this.$scopedSlots.default && this.$scopedSlots.default()?.[0]?.componentOptions?.propsData?.route) {
                this.hasRoutes = true;
            }
        },

        recalculateSlider() {
            window.setTimeout(() => {
                const activeItem = this.activeItem;
                this.activeItem = null;
                this.activeItem = activeItem;
            }, 0);
        },

        updateActiveItem() {
            this.$nextTick().then(() => {
                const firstActiveTabItem = this.$children.find((child) => {
                    return child.$el.nodeType === 1 && child.$el.classList.contains('sw-tabs-item--active');
                });

                if (!firstActiveTabItem) {
                    return;
                }

                this.activeItem = this.$children.indexOf(firstActiveTabItem);
                if (!this.firstScroll) {
                    this.scrollToItem(firstActiveTabItem);
                }
                this.firstScroll = true;
            });
        },

        scrollTo(direction) {
            if (!['left', 'right'].includes(direction)) {
                return;
            }

            const tabContent = this.$refs.swTabContent;
            const tabContentWidth = tabContent.offsetWidth;

            if (direction === 'right') {
                tabContent.scrollLeft += (tabContentWidth / 2);
                return;
            }
            tabContent.scrollLeft += -(tabContentWidth / 2);
        },

        checkIfNeedScroll() {
            const tabContent = this.$refs.swTabContent;
            this.isScrollable = tabContent.scrollWidth !== tabContent.offsetWidth;
        },

        setActiveItem(item) {
            this.$emit('new-item-active', item);
            this.active = item.name;
            this.updateActiveItem();
        },

        scrollToItem(item) {
            const tabContent = this.$refs.swTabContent;
            const tabContentWidth = tabContent.offsetWidth;
            const itemOffset = item.$el.offsetLeft;
            const itemWidth = item.$el.clientWidth;

            if ((tabContentWidth / 2) < itemOffset) {
                const scrollWidth = itemOffset - (tabContentWidth / 2) + (itemWidth / 2);
                tabContent.scrollLeft = scrollWidth;
            }
        },

        addScrollbarOffset() {
            this.scrollbarOffset = dom.getScrollbarHeight(this.$refs.swTabContent);
        },
    },
});
