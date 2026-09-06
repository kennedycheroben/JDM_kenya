document.addEventListener('DOMContentLoaded', () => {
    const category = document.getElementById('category');
    const section = document.getElementById('sportsFields');
    const dob = document.getElementById('sports_date_of_birth');
    const guardian = document.getElementById('guardianFields');
    if (!category || !section || !dob || !guardian) return;

    const updateGuardian = () => {
        const birth = dob.value ? new Date(`${dob.value}T00:00:00`) : null;
        const now = new Date();
        let age = birth ? now.getFullYear() - birth.getFullYear() : 18;
        if (birth && (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate()))) age--;
        const isMinor = Boolean(birth && age < 18);
        guardian.classList.toggle('d-none', !isMinor);
        guardian.querySelectorAll('.guardian-required').forEach((field) => field.required = isMinor);
    };
    const updateSports = () => {
        const active = category.value === 'sports_ministry';
        section.classList.toggle('d-none', !active);
        section.querySelectorAll('.sports-required').forEach((field) => field.required = active);
        if (!active) guardian.querySelectorAll('.guardian-required').forEach((field) => field.required = false);
        else updateGuardian();
    };
    category.addEventListener('change', updateSports);
    dob.addEventListener('change', updateGuardian);
    updateSports();
});
