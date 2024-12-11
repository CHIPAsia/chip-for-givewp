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
     * CHIP gateway api.
     */
    const chipGatewayApi = {
      clientKey: "",
      secureData: "",
      async submit() {
        if (!this.clientKey) {
          return {
            error: "chipGatewayApi clientKey is required.",
          };
        }
        if (this.secureData.length === 0) {
          return {
            error: "chipGatewayApi data is required.",
          };
        }
        return {
          transactionId: `chip_transaction-${Date.now()}`,
        };
      },
    };
  
    /**
     * Rendering gateway fields (without jsx).
     *
     * This renders a simple div with a label and input.
     *
     * @see https://react.dev/reference/react/createElement
     */
    function ChipGatewayFields() {
      return ReactElement("span", null,  __("You will be redirected to CHIP Payment Gateway.", "chip-for-givewp"));
      // return window.wp.element.createElement(
      //   "div",
      //   {},
      //   window.wp.element.createElement(
      //     "label",
      //     {
      //       htmlFor: "chip-gateway-id",
      //       style: { display: "block", border: "none" },
      //     },
      //     "CHIP Gateway Label",
      //     window.wp.element.createElement("input", {
      //       className: "chip-gateway",
      //       type: "text",
      //       name: "chip-gateway-id",
      //       onChange(e) {
      //         chipGatewayApi.secureData = e.target.value;
      //       },
      //     })
      //   )
      // );
    }
  
    /**
     * Front-end gateway object.
     */
    const ChipGateway = {
      id: 'chip_block',
      // initialize() {
      //   const { clientKey } = this.settings;
  
      //   chipGatewayApi.clientKey = clientKey;
      // },
      async beforeCreatePayment(values) {
        console.log(values)
        return {
          chipGatewayIntent: 'chip-gateway-intent',
        };  
      },

      //   // try {
      //   //   return {
      //   //       "chip-gateway-id": '123',
      //   //     };
          
      //   // } catch (error) {
      //   //     console.error('CHIP payment creation failed:', error);
      //   //     throw new Error(
      //   //         __('Failed to process CHIP payment. Please try again.', 'chip-for-givewp')
      //   //     );
      //   // }

      //   // Trigger form validation and wallet collection
      //   // const { transactionId, error: submitError } =
      //   //   await chipGatewayApi.submit();
  
      //   // if (submitError) {
      //   //   throw new Error(submitError);
      //   // }
  
      //   // return {
      //   //   // "chip-gateway-id": transactionId,
      //   //   "chip-gateway-id": '123',
      //   // };
      // },
      Fields() {
        return ReactElement(ChipGatewayFields);
      },
    };
  
    /**
     * The final step is to register the front-end gateway with GiveWP.
     */
    window.givewp.gateways.register(ChipGateway);
  })();
  