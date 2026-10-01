export default function Heading({ title, description }: { title: string; description?: string }) {
    return (
        <div className="mb-2 space-y-1">
            <h1 className="text-2xl font-semibold tracking-tight text-white">{title}</h1>
            {description && <p className="text-sm text-[#8a8a8a]">{description}</p>}
        </div>
    );
}
