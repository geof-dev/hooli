{{--
    The hero's phone: Hooli scanning the back of a body lotion, then explaining it. The ingredients are typical of
    such a product; the notes say only what EU rules and assessments say. The animation lives in app.css (.phone).
--}}
<figure class="phone" role="img" aria-label="{{ __('An example of a Hooli scan. The camera reads a body lotion’s ingredient list, then Hooli explains it: for someone planning a pregnancy, two ingredients are worth knowing about, propylparaben and parfum, and three safer swaps are suggested.') }}">
    <div class="phone-screen" aria-hidden="true">
        <div class="phone-island"></div>

        <div class="camera">
            <p class="camera-hint">{{ __('Point at the ingredients') }}</p>
            <div class="label">
                <b>Ingredients:</b> Aqua, Glycerin, Cetearyl Alcohol, Caprylic/Capric Triglyceride, Glyceryl Stearate,
                Butyrospermum Parkii Butter, Dimethicone, <mark>Propylparaben</mark>, Phenoxyethanol, Carbomer,
                <mark>Parfum</mark>, Tocopherol, Sodium Hydroxide, Disodium EDTA.
                <span class="scan-frame"></span>
                <span class="scan-line"></span>
            </div>
        </div>

        <div class="sheet">
            <div class="sheet-handle"></div>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-display text-[21px] leading-tight">{{ __('Body lotion') }}</p>
                    <p class="text-[12px] text-ink-2">{{ __('14 ingredients, read just now') }}</p>
                </div>
                <span class="tag mt-1 border border-line text-ink-2">{{ __('Example') }}</span>
            </div>

            <div class="sheet-verdict">
                <p class="font-semibold">{{ __('For you: go easy') }}</p>
                <p class="text-[12px] leading-snug">{{ __('2 ingredients worth knowing about while you plan a pregnancy.') }}</p>
            </div>

            <div class="sheet-row">
                <div class="flex items-center justify-between gap-2"><b class="font-semibold">Propylparaben</b><span class="tag tag-know">{{ __('Worth knowing') }}</span></div>
                <p class="text-[12px] leading-snug text-ink-2">{{ __('A preservative studied as a possible hormone disruptor. EU rules limit how much a product can contain.') }}</p>
            </div>
            <div class="sheet-row">
                <div class="flex items-center justify-between gap-2"><b class="font-semibold">Parfum</b><span class="tag tag-know">{{ __('Worth knowing') }}</span></div>
                <p class="text-[12px] leading-snug text-ink-2">{{ __('A fragrance blend. Its ingredients don’t have to be listed.') }}</p>
            </div>
            <div class="sheet-row">
                <div class="flex items-center justify-between gap-2"><b class="font-semibold">Glycerin</b><span class="tag tag-fine">{{ __('No concern') }}</span></div>
                <p class="text-[12px] leading-snug text-ink-2">{{ __('Keeps skin moist.') }}</p>
            </div>

            <p class="sheet-swap">{{ __('See 3 safer swaps') }}</p>
        </div>
    </div>
</figure>
