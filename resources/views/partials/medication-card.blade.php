{{--
    A medication check, as the app will show it. Cyproterone acetate is one of the progestins the European Medicines
    Agency restricted in 2020 because of meningioma; the risk grows with the dose and the length of treatment.
--}}
<figure class="mx-auto w-full max-w-[400px] rounded-[28px] bg-cream p-6 shadow-[0_0_0_1px_var(--color-line),0_30px_60px_-30px_rgb(42_64_51/.35)] sm:p-7" role="img"
        aria-label="{{ __('An example of a medication check for cyproterone acetate: Hooli notes the link with meningioma and suggests three questions for the doctor.') }}">
    <div aria-hidden="true" class="grid gap-4 text-[16px]">
        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
            <div>
                <p class="text-[13px] font-semibold text-sage-deep">{{ __('Medication check') }}</p>
                <p class="font-display text-[26px] leading-tight">Cyproterone acetate</p>
                <p class="text-[14px] text-ink-2">{{ __('Tablets, 50 mg') }}</p>
            </div>
            <span class="tag tag-know mt-1">{{ __('Talk to your doctor') }}</span>
        </div>
        <p class="leading-snug">{{ __('Linked to a higher risk of meningioma. The risk grows with the dose and the length of treatment.') }}</p>
        <div class="rounded-[18px] bg-mist p-4">
            <p class="text-[14px] font-semibold text-sage-deep">{{ __('Questions for your next appointment') }}</p>
            <ul class="mt-2 grid gap-1.5 text-[15px] leading-snug">
                <li class="flex gap-2"><span class="mt-[.55em] size-1.5 shrink-0 rounded-full bg-sage"></span>{{ __('Is this still the right treatment for me?') }}</li>
                <li class="flex gap-2"><span class="mt-[.55em] size-1.5 shrink-0 rounded-full bg-sage"></span>{{ __('Could a lower dose or another option work?') }}</li>
                <li class="flex gap-2"><span class="mt-[.55em] size-1.5 shrink-0 rounded-full bg-sage"></span>{{ __('Should I have a brain MRI at some point?') }}</li>
            </ul>
        </div>
        <p class="text-[13px] text-ink-2">{{ __('Source: European Medicines Agency, 2020') }}</p>
    </div>
</figure>
