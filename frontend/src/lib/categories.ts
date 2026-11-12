import type { Category } from "@/lib/api/domain";

/** Semua kategori (root + seluruh turunannya) dalam satu daftar datar. */
export function flattenCategories(categories: Category[]): Category[] {
  return categories.flatMap((category) => [category, ...flattenCategories(category.children ?? [])]);
}

/** Cari kategori berdasarkan slug pada struktur pohon. */
export function findCategoryBySlug(categories: Category[], slug: string): Category | null {
  return flattenCategories(categories).find((category) => category.slug === slug) ?? null;
}

/** Slug kategori induk dari sebuah slug (null bila kategori root atau tidak ada). */
export function parentSlugOf(categories: Category[], slug: string): string | null {
  const walk = (list: Category[], parent: Category | null): string | null => {
    for (const category of list) {
      if (category.slug === slug) {
        return parent?.slug ?? null;
      }

      const found = walk(category.children ?? [], category);

      if (found !== null) {
        return found;
      }
    }

    return null;
  };

  return walk(categories, null);
}
