<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">
            <div class="card">
                <div class="card-body p-5">
                    <i class="bi bi-tools display-3 text-primary"></i>
                    <h1 class="h3 mt-3">المنصة تحت الصيانة</h1>
                    <p class="text-muted">نقوم حالياً بتحديث المنصة. نعتذر عن الإزعاج وسنعود قريباً بإذن الله.</p>
                    <?php if (trim((string) settings('contact_phone', '')) !== ''): ?>
                        <p class="small text-muted mb-0">للتواصل:
                            <a class="link-light" dir="ltr" href="https://wa.me/<?= e(contact_phone_intl()) ?>" target="_blank" rel="noopener"><?= e(contact_phone_display()) ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
