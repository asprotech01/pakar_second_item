document.addEventListener('DOMContentLoaded', () => {
	const form = document.querySelector('[data-inspection-form]');

	if (!form) return;

	const steps = Array.from(form.querySelectorAll('[data-question-step]'));
	const previousButton = form.querySelector('[data-previous]');
	const nextButton = form.querySelector('[data-next]');
	const submitButton = form.querySelector('[data-submit-evaluation]');
	const controls = form.querySelector('[data-inspection-controls]');
	const countLabel = form.querySelector('[data-step-count]');
	const progressBar = form.querySelector('[data-progress-bar]');
	const categoryItems = Array.from(form.querySelectorAll('[data-category-index]'));
	let activeIndex = 0;

	if (!steps.length || !controls) return;

	const selectedAnswer = (step) => step.querySelector('input[type="radio"]:checked');

	const syncEvidenceFields = (step) => {
		const selected = selectedAnswer(step);
		const sourceField = step.querySelector('.source-select');
		const sourceSelect = step.querySelector('.source-select select');
		const isUnknown = selected?.value === 'unknown';

		sourceField.hidden = isUnknown;
		sourceSelect.disabled = isUnknown;
		if (isUnknown) sourceSelect.value = 'UNKNOWN';
	};

	const showStep = (index) => {
		activeIndex = index;
		steps.forEach((step, stepIndex) => {
			step.hidden = stepIndex !== activeIndex;
			step.querySelectorAll('input[type="radio"]').forEach((input) => {
				input.required = stepIndex === activeIndex;
			});
			syncEvidenceFields(step);
		});

		const selected = selectedAnswer(steps[activeIndex]);
		const categoryName = steps[activeIndex].dataset.category;
		const completedCount = String(activeIndex + 1).padStart(2, '0');
		countLabel.textContent = completedCount;
		progressBar.style.width = `${((activeIndex + 1) / steps.length) * 100}%`;
		categoryItems.forEach((item) => {
			item.classList.toggle('is-current', item.textContent.trim().includes(categoryName));
		});
		previousButton.disabled = activeIndex === 0;
		previousButton.hidden = activeIndex === 0;
		nextButton.hidden = activeIndex === steps.length - 1;
		nextButton.disabled = !selected;
		submitButton.hidden = activeIndex !== steps.length - 1;
		submitButton.disabled = !selected;
	};

	form.addEventListener('change', (event) => {
		const step = event.target.closest('[data-question-step]');
		if (!step) return;

		syncEvidenceFields(step);
		if (step === steps[activeIndex]) {
			const hasAnswer = Boolean(selectedAnswer(step));
			nextButton.disabled = !hasAnswer;
			submitButton.disabled = !hasAnswer;
		}
	});

	previousButton.addEventListener('click', () => showStep(Math.max(0, activeIndex - 1)));
	nextButton.addEventListener('click', () => {
		if (selectedAnswer(steps[activeIndex])) showStep(Math.min(steps.length - 1, activeIndex + 1));
	});

	form.addEventListener('submit', (event) => {
		const unansweredIndex = steps.findIndex((step) => !selectedAnswer(step));
		if (unansweredIndex !== -1) {
			event.preventDefault();
			showStep(unansweredIndex);
			steps[unansweredIndex].querySelector('input[type="radio"]')?.focus();
			return;
		}

		submitButton.disabled = true;
		submitButton.innerHTML = 'Menghitung hasil <span aria-hidden="true">…</span>';
	});

	controls.hidden = false;
	showStep(0);
});
