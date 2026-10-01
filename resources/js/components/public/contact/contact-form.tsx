import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type ContactFormData = {
    name: string;
    email: string;
    subject: string;
    message: string;
};

export default function ContactForm() {
    const { flash } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm<ContactFormData>({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const successMessage = flash?.contact_success ?? null;

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (processing) {
            return;
        }
        post(route('contact.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div className="cc-ac-panel">
            <div className="cc-ac-panel__body">
                <h2 className="cc-ac-title !text-xl">Send a message</h2>
                <p className="cc-ac-lede !mt-2 !text-sm">
                    Use the form below — we do not publish a separate support phone or address for Campus Coin.
                </p>

                {successMessage && (
                    <div className="cc-contact-success mt-4" role="status" aria-live="polite">
                        {successMessage}
                    </div>
                )}

                <form className="cc-contact-form mt-5" onSubmit={submit} noValidate>
                    <div className="cc-contact-field">
                        <Label htmlFor="contact-name">Name</Label>
                        <Input
                            id="contact-name"
                            name="name"
                            value={data.name}
                            autoComplete="name"
                            onChange={(e) => setData('name', e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={errors.name ? 'contact-name-error' : undefined}
                            disabled={processing}
                        />
                        <InputError id="contact-name-error" message={errors.name} />
                    </div>

                    <div className="cc-contact-field">
                        <Label htmlFor="contact-email">Email</Label>
                        <Input
                            id="contact-email"
                            type="email"
                            name="email"
                            value={data.email}
                            autoComplete="email"
                            onChange={(e) => setData('email', e.target.value)}
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'contact-email-error' : undefined}
                            disabled={processing}
                        />
                        <InputError id="contact-email-error" message={errors.email} />
                    </div>

                    <div className="cc-contact-field">
                        <Label htmlFor="contact-subject">Subject</Label>
                        <Input
                            id="contact-subject"
                            name="subject"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            aria-invalid={Boolean(errors.subject)}
                            aria-describedby={errors.subject ? 'contact-subject-error' : undefined}
                            disabled={processing}
                        />
                        <InputError id="contact-subject-error" message={errors.subject} />
                    </div>

                    <div className="cc-contact-field">
                        <Label htmlFor="contact-message">Message</Label>
                        <Textarea
                            id="contact-message"
                            name="message"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            aria-invalid={Boolean(errors.message)}
                            aria-describedby={errors.message ? 'contact-message-error' : undefined}
                            disabled={processing}
                            rows={6}
                        />
                        <InputError id="contact-message-error" message={errors.message} />
                    </div>

                    <Button type="submit" className="min-h-11 w-full sm:w-auto" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" aria-hidden />}
                        {processing ? 'Sending…' : 'Send message'}
                    </Button>
                </form>
            </div>
        </div>
    );
}
