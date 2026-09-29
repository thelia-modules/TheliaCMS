/**
 * Dresses the element holding the page in the canvas with the classes the
 * theme wraps its content in, without saving them with the page.
 *
 * A theme writes the style of its text as `.page__content p`, `.page__content`
 * being the container its page template puts around the published content.
 * The canvas has no page template, so without that class the text is not
 * styled the way visitors will read it.
 *
 * The classes cannot be added to the wrapper component: GrapesJS saves them
 * with the page, which would nest the container twice on the front and carry
 * it into the next theme. They are written on the element the wrapper renders
 * to, and written again each time GrapesJS rewrites the attributes of that
 * element from the component, which it does whenever its classes or its
 * attributes change.
 */
export default (editor, { classes = [] } = {}) => {
    if (classes.length === 0) {
        return;
    }

    const components = editor.Components;
    const WrapperView = components.getType("wrapper").view;
    const dress = (element) => element?.classList.add(...classes);

    components.addType("wrapper", {
        view: {
            updateAttributes(...args) {
                WrapperView.prototype.updateAttributes.apply(this, args);
                dress(this.el);
            },
            updateClasses(...args) {
                WrapperView.prototype.updateClasses.apply(this, args);
                dress(this.el);
            },
        },
    });
};
