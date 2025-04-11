<div
    x-data="{ show: false, message: '' }"
    x-show="show"
    x-init="$watch('show', val => { if(val) setTimeout(() => show = false, 3000) })"
    x-transition
    x-text="message"
    class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-md text-sm z-50"
    style="display: none;"
    id="global-toast"
></div>
