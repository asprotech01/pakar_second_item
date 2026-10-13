document.addEventListener('DOMContentLoaded', () => {
	document.addEventListener('click', (event) => {
		const openButton = event.target.closest('[data-open-device-preview]');
		if (openButton) {
			document.getElementById(openButton.dataset.openDevicePreview)?.showModal();
			return;
		}

		const closeButton = event.target.closest('[data-close-device-preview]');
		if (closeButton) closeButton.closest('dialog')?.close();
	});

	const workspaceForm = document.querySelector('[data-workspace-assessment]');

	if (workspaceForm) {
		const searchInput = workspaceForm.querySelector('[data-device-search]');
		const deviceIdInput = workspaceForm.querySelector('[data-device-id]');
		const resultsList = workspaceForm.querySelector('[data-device-results]');
		const searchStatus = workspaceForm.querySelector('[data-search-status]');
		const selectedDevice = workspaceForm.querySelector('[data-selected-device]');
		const selectedName = workspaceForm.querySelector('[data-selected-name]');
		const selectedManufacturer = workspaceForm.querySelector('[data-selected-manufacturer]');
		const selectedStorage = workspaceForm.querySelector('[data-selected-storage]');
		const storageField = workspaceForm.querySelector('[data-storage-field]');
		const storageSelect = workspaceForm.querySelector('[data-storage-select]');
		const endpoint = workspaceForm.dataset.autocompleteUrl;
		let searchTimer;
		let activeRequest;

		const storageVariants = (values) => {
			const flattened = [];
			const collectValues = (value) => {
				if (Array.isArray(value)) {
					value.forEach(collectValues);
				} else if (typeof value === 'string' || typeof value === 'number') {
					String(value).split(',').forEach((variant) => {
						const canonicalValue = variant.trim();
						if (canonicalValue && !flattened.includes(canonicalValue)) flattened.push(canonicalValue);
					});
				}
			};

			collectValues(values);
			return flattened;
		};

		const clearSelectedDevice = () => {
			deviceIdInput.value = '';
			selectedDevice.hidden = true;
			selectedName.textContent = '';
			selectedManufacturer.textContent = '';
			selectedStorage.textContent = '';
			storageField.hidden = true;
			storageSelect.replaceChildren(new Option('Pilih storage', ''));
			storageSelect.disabled = true;
			storageSelect.required = false;
			searchInput.setCustomValidity('');
		};

		const selectDevice = (device) => {
			const variants = storageVariants(device.storage_variants);
			deviceIdInput.value = device.id;
			searchInput.value = device.name;
			searchInput.setCustomValidity('');
			selectedName.textContent = device.name;
			selectedManufacturer.textContent = device.manufacturer_name || 'Merek tidak tersedia';
			selectedStorage.textContent = variants.length ? variants.join(' · ') : 'Informasi storage tidak tersedia';
			selectedDevice.hidden = false;
			resultsList.replaceChildren();
			resultsList.hidden = true;
			searchInput.setAttribute('aria-expanded', 'false');
			storageField.hidden = variants.length === 0;
			storageSelect.replaceChildren(new Option('Pilih storage', ''));

			variants.forEach((variant) => storageSelect.add(new Option(variant, variant)));
			storageSelect.disabled = variants.length === 0;
			storageSelect.required = variants.length > 0;
			if (variants.length === 1) storageSelect.value = variants[0];
			searchStatus.textContent = 'Perangkat dipilih. Pastikan profil pemeriksaan sesuai dengan perangkat.';
		};

		const renderDevices = (devices) => {
			resultsList.replaceChildren();

			devices.forEach((device) => {
				const variants = storageVariants(device.storage_variants);
				const option = document.createElement('button');
				const title = document.createElement('strong');
				const details = document.createElement('span');
				const storage = document.createElement('small');

				option.type = 'button';
				option.className = 'workspace-device-option';
				option.setAttribute('role', 'option');
				option.setAttribute('aria-selected', 'false');
				title.textContent = device.name;
				details.textContent = device.manufacturer_name || 'Merek tidak tersedia';
				storage.textContent = variants.length ? variants.join(' · ') : 'Storage tidak tersedia';
				option.append(title, details, storage);
				option.addEventListener('click', () => selectDevice(device));
				resultsList.append(option);
			});

			resultsList.hidden = devices.length === 0;
			searchInput.setAttribute('aria-expanded', String(devices.length > 0));
			searchStatus.textContent = devices.length ? `${devices.length} perangkat ditemukan.` : 'Perangkat tidak ditemukan. Coba nama atau merek lain.';
		};

		const initialDevice = workspaceForm.dataset.initialDevice;
		if (initialDevice) {
			try {
				const device = JSON.parse(initialDevice);
				selectDevice(device);
				const previousStorage = workspaceForm.dataset.initialStorage;
				if (previousStorage && Array.from(storageSelect.options).some((option) => option.value === previousStorage)) {
					storageSelect.value = previousStorage;
				}
			} catch {
				searchStatus.textContent = 'Perangkat sebelumnya tidak dapat dipulihkan. Silakan cari dan pilih kembali.';
				clearSelectedDevice();
			}
		}

		searchInput.addEventListener('input', () => {
			clearTimeout(searchTimer);
			activeRequest?.abort();
			clearSelectedDevice();
			resultsList.replaceChildren();
			resultsList.hidden = true;
			searchInput.setAttribute('aria-expanded', 'false');

			const query = searchInput.value.trim();
			if (query.length < 2) {
				searchStatus.textContent = 'Ketik minimal 2 karakter untuk mencari perangkat tersimpan.';
				return;
			}

			searchStatus.textContent = 'Mencari perangkat tersimpan…';
			searchTimer = setTimeout(async () => {
				activeRequest = new AbortController();

				try {
					const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
						headers: { Accept: 'application/json' },
						signal: activeRequest.signal,
					});
					if (!response.ok) throw new Error('Device search request failed.');

					const payload = await response.json();
					renderDevices(Array.isArray(payload.devices) ? payload.devices : []);
				} catch (error) {
					if (error.name === 'AbortError') return;

					resultsList.replaceChildren();
					resultsList.hidden = true;
					searchInput.setAttribute('aria-expanded', 'false');
					searchStatus.textContent = 'Pencarian perangkat gagal. Silakan coba lagi.';
				}
			}, 250);
		});

		workspaceForm.addEventListener('submit', (event) => {
			if (!deviceIdInput.value) {
				event.preventDefault();
				searchInput.setCustomValidity('Pilih perangkat dari hasil pencarian sebelum memulai assessment.');
				searchInput.reportValidity();
			}
		});
	}

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
