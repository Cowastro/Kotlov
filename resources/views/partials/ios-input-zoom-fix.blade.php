{{-- iOS Safari zooms the page into any field with text under 16px and stays zoomed: the form then looks
     stretched past the screen. Phones and tablets get at least 16px in every text field; user zoom stays on. --}}
<style>
    @media (max-width: 991.98px) {
        input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]):not([type="file"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]):not([type="hidden"]),
        select,
        textarea {
            font-size: max(16px, 1em) !important;
        }
    }
</style>
