jQuery( function ( $ ) {

  $('#chip-refund-button').on('click', function (e) {
    e.preventDefault();
    donation_id = $(this).data('donation-id');
    nonce       = $(this).data('nonce');

    postData = {
      action: 'gwp_chip_refund',
      gwp_chip_refund_payment: nonce,
      donation_id: donation_id,
    };

    noteContainer     = $('#give-payment-note');
    noteTypeContainer = $('#donation_note_type');

    $.ajax({
      type: 'POST',
      data: postData,
      url:  ajaxurl,
      beforeSend: function () {
        noteContainer.prop('disabled', true);
        $('#chip-refund-button').prop('disabled', true);
        $('#chip-refund-button').html("Refunding...");
      },
      success: function (response) {
        $('#give-payment-notes-inner').append(response);
        $('.give-no-payment-notes').hide();
        $('#chip-refund-button').html("Refund successful");
        window.location.reload(true);
      },
    })
      .fail(function (data) {
        if (window.console && window.console.log) {
          console.log(data);
        }
        $('#chip-refund-button').html("Refund failed!")
      })
      .always(function () {
        noteContainer.prop('disabled', false);
      });
  });
});