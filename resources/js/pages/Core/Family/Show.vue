<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import BookCard from '@/components/Library/BookCard.vue'
import ToolCard from '@/components/Garage/ToolCard.vue'
import type { Book, Bookshelf } from '@/types/library'
import type { Tool, ToolShelf } from '@/types/garage'
import type { Member, MemberFilters, MemberTab } from '@/types/family'

const props = defineProps<{
  member: Member
  /** True on the page of the signed-in member. */
  own: boolean
  /** Tab open on load. */
  tab: MemberTab
  /** Their bookshelves, by position, for the filter. */
  bookshelves: Bookshelf[]
  /** Their books (on the filtered bookshelf), by title. */
  books: Book[]
  /** Their tool shelves, by position, for the filter. */
  toolShelves: ToolShelf[]
  /** Their tools (on the filtered shelf), by name. */
  tools: Tool[]
  filters: MemberFilters
}>()

const breadcrumb = computed(() => [
  { label: 'La famille', to: route('family.index', undefined, false) },
  { label: props.member.name },
])

const activeTab = ref<MemberTab>(props.tab)
watch(() => props.tab, (tab) => {
  activeTab.value = tab
})

const tabs = [
  { label: 'Bibliothèque', icon: 'i-lucide-library-big', value: 'library', slot: 'library' as const },
  { label: 'Garage', icon: 'i-lucide-warehouse', value: 'garage', slot: 'garage' as const },
]

const filteredBookshelf = computed(() => props.bookshelves.find((bookshelf) => bookshelf.id === props.filters.bookshelf) ?? null)
const filteredShelf = computed(() => props.toolShelves.find((shelf) => shelf.id === props.filters.shelf) ?? null)

const bookshelfFilters = computed(() => [
  {
    key: 'all',
    label: 'Tous les livres',
    to: route('family.members.show', { member: props.member.id }, false),
    active: props.filters.bookshelf === null,
  },
  ...props.bookshelves.map((bookshelf) => ({
    key: `bookshelf-${bookshelf.id}`,
    label: bookshelf.name,
    to: route('family.members.show', { member: props.member.id, bookshelf: bookshelf.id }, false),
    active: bookshelf.id === props.filters.bookshelf,
  })),
])

const shelfFilters = computed(() => [
  {
    key: 'all',
    label: 'Tous les outils',
    to: route('family.members.show', { member: props.member.id, tab: 'garage' }, false),
    active: props.filters.shelf === null,
  },
  ...props.toolShelves.map((shelf) => ({
    key: `shelf-${shelf.id}`,
    label: shelf.name,
    to: route('family.members.show', { member: props.member.id, tab: 'garage', shelf: shelf.id }, false),
    active: shelf.id === props.filters.shelf,
  })),
])

const noBooks = computed(() => (props.own ? "Vous n'avez encore aucun livre." : `${props.member.name} n'a encore aucun livre.`))
const noTools = computed(() => (props.own ? "Vous n'avez encore aucun outil." : `${props.member.name} n'a encore aucun outil.`))
</script>

<template>
  <DashboardLayout
    :title="member.name"
    :breadcrumb="breadcrumb"
  >
    <div class="mx-auto w-full max-w-6xl">
      <UTabs
        v-model="activeTab"
        :items="tabs"
        variant="link"
        class="w-full"
      >
        <template #library>
          <div class="space-y-6 pt-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <nav
                v-if="bookshelves.length > 0"
                aria-label="Filtrer par étagère"
                class="flex flex-wrap gap-2"
              >
                <UButton
                  v-for="item in bookshelfFilters"
                  :key="item.key"
                  :to="item.to"
                  :label="item.label"
                  :color="item.active ? 'primary' : 'neutral'"
                  :variant="item.active ? 'solid' : 'outline'"
                  :aria-current="item.active ? 'page' : undefined"
                  size="sm"
                  data-testid="bookshelf-filter"
                />
              </nav>
              <UButton
                v-if="own"
                :to="route('library.books.index', undefined, false)"
                icon="i-lucide-settings-2"
                color="neutral"
                variant="outline"
                class="ms-auto"
                data-testid="manage-books"
              >
                Gérer mes livres
              </UButton>
            </div>

            <UEmpty
              v-if="books.length === 0 && filteredBookshelf === null"
              icon="i-lucide-book-open"
              :title="noBooks"
              variant="naked"
            />
            <UEmpty
              v-else-if="books.length === 0"
              icon="i-lucide-book-open"
              :title="`Aucun livre sur l'étagère « ${filteredBookshelf?.name} ».`"
              variant="naked"
            />
            <ul
              v-else
              class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
            >
              <li
                v-for="book in books"
                :key="book.id"
              >
                <BookCard :book="book" />
              </li>
            </ul>
          </div>
        </template>

        <template #garage>
          <div class="space-y-6 pt-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <nav
                v-if="toolShelves.length > 0"
                aria-label="Filtrer par étagère"
                class="flex flex-wrap gap-2"
              >
                <UButton
                  v-for="item in shelfFilters"
                  :key="item.key"
                  :to="item.to"
                  :label="item.label"
                  :color="item.active ? 'primary' : 'neutral'"
                  :variant="item.active ? 'solid' : 'outline'"
                  :aria-current="item.active ? 'page' : undefined"
                  size="sm"
                  data-testid="shelf-filter"
                />
              </nav>
              <UButton
                v-if="own"
                :to="route('garage.tools.index', undefined, false)"
                icon="i-lucide-settings-2"
                color="neutral"
                variant="outline"
                class="ms-auto"
                data-testid="manage-tools"
              >
                Gérer mes outils
              </UButton>
            </div>

            <UEmpty
              v-if="tools.length === 0 && filteredShelf === null"
              icon="i-lucide-wrench"
              :title="noTools"
              variant="naked"
            />
            <UEmpty
              v-else-if="tools.length === 0"
              icon="i-lucide-wrench"
              :title="`Aucun outil sur l'étagère « ${filteredShelf?.name} ».`"
              variant="naked"
            />
            <ul
              v-else
              class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
            >
              <li
                v-for="tool in tools"
                :key="tool.id"
              >
                <ToolCard :tool="tool" />
              </li>
            </ul>
          </div>
        </template>
      </UTabs>
    </div>
  </DashboardLayout>
</template>
