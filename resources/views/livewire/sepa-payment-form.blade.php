<div x-data="{
  stripe: null,
  paymentElement: null,
  processing: false,
  error: null,
  handleSubmit() {
    this.processing = true
    this.error = null

    this.stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: '{{ $returnUrl }}',
          payment_method_data: {
            billing_details: {
              name: '{{ addslashes($this->billing?->first_name) }} {{ addslashes($this->billing?->last_name) }}',
              email: '{{ addslashes($this->billing?->contact_email) }}',
            }
          }
        },
      }).then(result => {
        if (result.error) {
          this.error = result.error.message
          this.processing = false
        }
      }).catch(() => {
        this.processing = false
      })
  },
  init() {
    this.stripe = Stripe('{{ config('services.stripe.public_key') }}');

    elements = this.stripe.elements({
      clientSecret: '{{ $this->clientSecret }}'
    });

    this.paymentElement = elements.create('payment');
    this.paymentElement.mount(this.$refs.paymentElement);
  }
}">
  <div class="p-4 text-sm text-neutral-700 rounded-lg bg-neutral-50 border border-neutral-200 mb-4">
    <p class="font-medium mb-1">Prélèvement SEPA</p>
    <p class="text-neutral-500">Saisissez vos coordonnées bancaires. Le prélèvement sera effectué sous 2–5 jours ouvrés.</p>
  </div>

  <form x-ref="payment-form" x-on:submit.prevent="handleSubmit()">
    <div x-ref="paymentElement"></div>
    <div class="mt-4">
      <button
        class="flex items-center gap-2 px-5 py-3 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
        type="submit"
        x-bind:disabled="processing"
      >
        <span x-show="!processing">Autoriser le prélèvement</span>
        <span x-show="processing" class="block">
          <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </span>
        <span x-show="processing">Traitement en cours...</span>
      </button>
    </div>
    <div x-show="error" x-text="error" class="p-3 mt-4 text-sm text-red-700 rounded-lg bg-red-50"></div>
  </form>
</div>
