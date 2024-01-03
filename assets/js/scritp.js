jQuery(document).ready(function($) {
    $('#payPingDonate_Amount').on('input', function(event) {
      let inputVal = $(this).val().replace(/\D/g, ''); // Remove non-digit characters
  
      // Add commas as a separator every three digits from the right
      inputVal = inputVal.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  
      // Update the input value with the formatted number
      $(this).val(inputVal);
    });
  });

  
