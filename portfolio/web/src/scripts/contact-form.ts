/**
 * Kontaktformular mit Inline-Validierung, Honeypot und klarer Rückmeldung.
 * Die Anfrage landet im Dashboard-Posteingang und zusätzlich per Mail.
 */

import { sendContact, track } from '../lib/api';
import { sound } from './sound';

export function initContactForm(): void {
  const form = document.querySelector<HTMLFormElement>('[data-contact-form]');
  if (!form) return;

  const submit = form.querySelector<HTMLButtonElement>('[type="submit"]');
  const feedback = form.querySelector<HTMLElement>('[data-form-feedback]');
  const submitLabel = submit?.textContent ?? 'Absenden';

  const showFieldError = (name: string, message: string): void => {
    const hint = form.querySelector<HTMLElement>(`[data-error-for="${name}"]`);
    const field = form.querySelector<HTMLElement>(`[name="${name}"]`);
    if (hint) hint.textContent = message;
    field?.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
  };

  const clearErrors = (): void => {
    form.querySelectorAll<HTMLElement>('[data-error-for]').forEach((hint) => (hint.textContent = ''));
    form.querySelectorAll('[aria-invalid]').forEach((field) => field.setAttribute('aria-invalid', 'false'));
  };

  form.addEventListener('focusin', (event) => {
    const field = event.target as HTMLElement;
    if (field.getAttribute('aria-invalid') === 'true') {
      showFieldError(field.getAttribute('name') ?? '', '');
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();

    const data = new FormData(form);
    const payload = {
      name: String(data.get('name') ?? ''),
      email: String(data.get('email') ?? ''),
      subject: String(data.get('subject') ?? ''),
      budget: String(data.get('budget') ?? ''),
      message: String(data.get('message') ?? ''),
      website: String(data.get('website') ?? ''),
    };

    if (submit) {
      submit.disabled = true;
      submit.textContent = 'Wird gesendet …';
    }
    if (feedback) {
      feedback.textContent = '';
      feedback.className = 'text-sm';
    }

    const result = await sendContact(payload).catch(() => ({
      ok: false,
      error: 'Verbindung fehlgeschlagen. Bitte später erneut versuchen oder direkt eine E-Mail schreiben.',
      fields: undefined,
    }));

    if (submit) {
      submit.disabled = false;
      submit.textContent = submitLabel;
    }

    if (result.ok) {
      form.reset();
      sound.success();
      track('contact_open', {});
      if (feedback) {
        feedback.className = 'text-sm text-emerald-400';
        feedback.textContent = 'Danke – die Nachricht ist angekommen. Ich melde mich zeitnah zurück.';
      }
      return;
    }

    if (result.fields) {
      Object.entries(result.fields).forEach(([field, message]) => showFieldError(field, message));
    }
    if (feedback) {
      feedback.className = 'text-sm text-rose-400';
      feedback.textContent = result.error ?? 'Da ist etwas schiefgelaufen. Bitte noch einmal versuchen.';
    }
  });
}
