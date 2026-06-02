/**
 * Start with a Self-Executing Anonymous Function (IIFE) to avoid polluting and conflicting with the global namespace (encapsulation).
 * @see https://developer.mozilla.org/en-US/docs/Glossary/IIFE
 *
 * This won't be necessary if you're using a build system like webpack.
 */
(() => {

    const { __ } = window.wp.i18n
    const { createElement } = window.wp.element;

    const ReactElement = (type, props = {}, ...childs) => {
      return Object(createElement)(type, props, ...childs);
    }

    /**
     * Rendering gateway fields (without jsx).
     *
     * This renders a simple span with the customizable CHIP message.
     *
     * @see https://react.dev/reference/react/createElement
     */
    function ChipGatewayFields() {
      const content = window.gwp_chip_block && window.gwp_chip_block.content
        ? window.gwp_chip_block.content
        : __( "Complete your donation securely. You will be redirected to CHIP's payment page to finalize your transaction.", "chip-for-givewp" );

      return ReactElement("span", { dangerouslySetInnerHTML: { __html: content } });
    }

    /**
     * Front-end gateway object.
     */
    const ChipGateway = {
      id: 'chip_block',
      async beforeCreatePayment(values) {
        //console.log(values)
        return {
          chipGatewayIntent: 'chip-gateway-intent',
        };
      },
      Fields() {
        return ReactElement(ChipGatewayFields);
      },
    };

    /**
     * The final step is to register the front-end gateway with GiveWP.
     */
    window.givewp.gateways.register(ChipGateway);
  })();
