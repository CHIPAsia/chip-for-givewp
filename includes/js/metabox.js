jQuery( function ( $ ) {

  init_chip_meta();
  $("#chip_metabox_options input:radio").on("change", function() {
    init_chip_meta();
  });

  function init_chip_meta(){
    if ("enabled" === $("#chip_metabox_options input:radio:checked").val()){
      $("._give_chip-enable-billing-fields_field").show();
      $("._give_chip-content_field").show();
      $("._give_chip-secret-key_field").show();
      $("._give_chip-test-secret-key_field").show();
      $("._give_chip-brand-id_field").show();
      $("._give_chip-send-receipt_field").show();
      $("._give_chip-due-strict_field").show();
      $("._give_chip-due-strict-timing_field").show();
    } else {
      $("._give_chip-enable-billing-fields_field").hide();
      $("._give_chip-content_field").hide();
      $("._give_chip-secret-key_field").hide();
      $("._give_chip-test-secret-key_field").hide();
      $("._give_chip-brand-id_field").hide();
      $("._give_chip-send-receipt_field").hide();
      $("._give_chip-due-strict_field").hide();
      $("._give_chip-due-strict-timing_field").hide();
    }
  }
});