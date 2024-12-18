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
     * This renders a simple div with a label and input.
     *
     * @see https://react.dev/reference/react/createElement
     */
    function ChipGatewayFields() {
      return ReactElement("span", null,  __("You will be redirected to CHIP Payment Gateway.", "chip-for-givewp"));
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
  