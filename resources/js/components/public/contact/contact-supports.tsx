import { LifeBuoy, Lightbulb, MessageCircle } from 'lucide-react';

const supports = [
    {
        title: 'General questions',
        body: 'Questions about Campus Coin and how the student budgeting experience works.',
        icon: MessageCircle,
    },
    {
        title: 'Technical support',
        body: 'Problems using the product — tracking, budgets, imports, or account access issues.',
        icon: LifeBuoy,
    },
    {
        title: 'Feedback',
        body: 'Ideas and suggestions for improving Campus Coin for student life.',
        icon: Lightbulb,
    },
] as const;

export default function ContactSupports() {
    return (
        <div>
            <h2 className="cc-ac-title !text-xl">How we can help</h2>
            <p className="cc-ac-lede !mt-2 !text-sm">Choose a topic in your message — the form is the primary contact path.</p>
            <div className="cc-contact-supports mt-5">
                {supports.map((item) => {
                    const Icon = item.icon;
                    return (
                        <article key={item.title} className="cc-contact-support">
                            <span className="bg-primary/10 text-primary mb-2 inline-flex size-8 items-center justify-center rounded-lg" aria-hidden>
                                <Icon className="size-4" />
                            </span>
                            <h3>{item.title}</h3>
                            <p>{item.body}</p>
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
