import type { ReactNode } from "react";
import { AboutNav } from "@/components/about/AboutNav";
import { PageHeader } from "@/components/ui/PageHeader";

export interface LegalSection {
  heading: string;
  paragraphs?: string[];
  bullets?: string[];
}

/**
 * Kerangka dokumen legal (privasi / syarat) agar tampilan konsisten.
 * Konten diberikan oleh masing-masing halaman.
 */
export function LegalDocument({
  current,
  title,
  description,
  updatedAt,
  intro,
  sections,
  footer,
}: {
  current: string;
  title: string;
  description: string;
  updatedAt: string;
  intro: string;
  sections: LegalSection[];
  footer?: ReactNode;
}) {
  return (
    <div className="container-page py-10 lg:py-14">
      <AboutNav current={current} />

      <PageHeader eyebrow="Tentang" title={title} description={description} />

      <article className="mt-8 max-w-3xl space-y-8">
        <p className="rounded-[8px] border border-line bg-canvas px-4 py-3 text-sm text-ink-muted">
          {intro} Terakhir diperbarui: {updatedAt}.
        </p>

        {sections.map((section) => (
          <section key={section.heading} className="space-y-2">
            <h2 className="text-lg font-semibold text-ink">{section.heading}</h2>

            {section.paragraphs?.map((paragraph, index) => (
              <p key={index} className="text-sm leading-7 text-ink-muted">
                {paragraph}
              </p>
            ))}

            {section.bullets ? (
              <ul className="list-disc space-y-1.5 pl-5 text-sm leading-7 text-ink-muted">
                {section.bullets.map((item, index) => (
                  <li key={index}>{item}</li>
                ))}
              </ul>
            ) : null}
          </section>
        ))}

        {footer ? <div className="border-t border-line pt-4">{footer}</div> : null}
      </article>
    </div>
  );
}
