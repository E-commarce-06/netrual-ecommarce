import { computed, reactive, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useCart } from '@/composables/useCart'

interface WishlistItem {
  id: number
  product: {
    id: number
    name: string
    slug: string
    price: number
    special_price?: number
    image?: string
    is_in_stock: boolean
  }
  added_at: string
}

const wishlistItems = ref<WishlistItem[]>([])
const loading = ref(false)
const pendingProductIds = reactive(new Set<number>())
const optimisticWishlistState = reactive(new Map<number, boolean>())
let activeRequests = 0
let wishlistMutationVersion = 0
let wishlistFetchVersion = 0

const beginLoading = () => {
  activeRequests += 1
  loading.value = true
}

const finishLoading = () => {
  activeRequests = Math.max(0, activeRequests - 1)
  loading.value = activeRequests > 0
}

export function useWishlist() {
  const page = usePage()
  const wishlistCount = computed(() => {
    let count = wishlistItems.value.length

    optimisticWishlistState.forEach((shouldBeSaved, productId) => {
      const isConfirmed = wishlistItems.value.some(item => item.product.id === productId)
      if (shouldBeSaved && !isConfirmed) count += 1
      if (!shouldBeSaved && isConfirmed) count -= 1
    })

    return count
  })

  const isInWishlist = (productId: number) => {
    if (optimisticWishlistState.has(productId)) {
      return optimisticWishlistState.get(productId)!
    }

    return wishlistItems.value.some(item => item.product.id === productId)
  }

  const isWishlistPending = (productId: number) => pendingProductIds.has(productId)

  const fetchWishlist = async () => {
    const requestVersion = ++wishlistFetchVersion
    const mutationVersion = wishlistMutationVersion
    beginLoading()

    try {
      const response = await axios.get('/account/wishlist/get')

      // Do not let an older request overwrite newer data, or undo a click that
      // completed while this initial/background refresh was in flight.
      if (response.data && requestVersion === wishlistFetchVersion && mutationVersion === wishlistMutationVersion) {
        wishlistItems.value = response.data.items || []
      }
    } catch (error) {
      console.error('Error fetching wishlist:', error)
    } finally {
      finishLoading()
    }
  }

  const addToWishlist = async (productId: number) => {
    const auth = page.props.auth as any
    if (!auth || !auth.user) {
      router.visit('/login?redirect=' + encodeURIComponent(window.location.pathname))
      return { success: false, message: 'Please login to add items to wishlist' }
    }

    if (pendingProductIds.has(productId)) {
      return { success: false, message: 'Wishlist update already in progress' }
    }

    if (isInWishlist(productId)) {
      return { success: true, message: 'Already in wishlist' }
    }

    pendingProductIds.add(productId)
    optimisticWishlistState.set(productId, true)
    beginLoading()

    try {
      const response = await axios.post('/account/wishlist/add', {
        product_id: productId
      })

      if (response.data?.item) {
        if (!wishlistItems.value.some(item => item.product.id === productId)) {
          wishlistItems.value.push(response.data.item)
        }
        wishlistMutationVersion += 1
        return { success: true, message: response.data.message || 'Added to wishlist' }
      }

      return { success: false, message: 'Failed to add to wishlist' }
    } catch (error: any) {
      // The header fetch and a quick click can race. If the server already has
      // this product, refresh the canonical list instead of leaving the heart
      // in a failed/incorrect state.
      if (error.response?.status === 400 && error.response?.data?.message === 'Already in wishlist') {
        await fetchWishlist()
        const exists = wishlistItems.value.some(item => item.product.id === productId)
        return exists
          ? { success: true, message: 'Already in wishlist' }
          : { success: false, message: 'Failed to add to wishlist' }
      }

      console.error('Error adding to wishlist:', error)
      const message = error.response?.data?.message || 'Failed to add to wishlist'
      return { success: false, message }
    } finally {
      pendingProductIds.delete(productId)
      optimisticWishlistState.delete(productId)
      finishLoading()
    }
  }

  const removeFromWishlist = async (wishlistItemId: number) => {
    const itemIndex = wishlistItems.value.findIndex(item => item.id === wishlistItemId)
    const item = itemIndex >= 0 ? wishlistItems.value[itemIndex] : undefined
    const productId = item?.product.id

    if (productId !== undefined && pendingProductIds.has(productId)) {
      return { success: false, message: 'Wishlist update already in progress' }
    }

    if (productId !== undefined) {
      pendingProductIds.add(productId)
      optimisticWishlistState.set(productId, false)
      wishlistItems.value.splice(itemIndex, 1)
    }
    beginLoading()

    try {
      const response = await axios.delete(`/account/wishlist/item/${wishlistItemId}`)

      if (response.status === 200) {
        wishlistItems.value = wishlistItems.value.filter(existing => existing.id !== wishlistItemId)
        wishlistMutationVersion += 1
        return { success: true, message: 'Removed from wishlist' }
      }

      if (item && !wishlistItems.value.some(existing => existing.id === item.id)) {
        wishlistItems.value.splice(Math.min(itemIndex, wishlistItems.value.length), 0, item)
      }
      return { success: false, message: 'Failed to remove from wishlist' }
    } catch (error: any) {
      if (item && !wishlistItems.value.some(existing => existing.id === item.id)) {
        wishlistItems.value.splice(Math.min(itemIndex, wishlistItems.value.length), 0, item)
      }
      console.error('Error removing from wishlist:', error)
      const message = error.response?.data?.message || 'Failed to remove from wishlist'
      return { success: false, message }
    } finally {
      if (productId !== undefined) {
        pendingProductIds.delete(productId)
        optimisticWishlistState.delete(productId)
      }
      finishLoading()
    }
  }

  const toggleWishlist = async (productId: number) => {
    if (pendingProductIds.has(productId)) {
      return { success: false, message: 'Wishlist update already in progress' }
    }

    const item = wishlistItems.value.find(item => item.product.id === productId)

    if (item && isInWishlist(productId)) {
      return await removeFromWishlist(item.id)
    }

    return await addToWishlist(productId)
  }

  const moveToCart = async (wishlistItemId: number) => {
    beginLoading()
    try {
      const response = await axios.post(`/account/wishlist/item/${wishlistItemId}/move-to-cart`)

      if (response.status === 200) {
        wishlistItems.value = wishlistItems.value.filter(item => item.id !== wishlistItemId)
        wishlistMutationVersion += 1

        // Refresh cart count
        const { fetchCart } = useCart()
        await fetchCart()

        return { success: true, message: response.data.message || 'Moved to cart' }
      }

      return { success: false, message: 'Failed to move to cart' }
    } catch (error: any) {
      console.error('Error moving to cart:', error)
      const message = error.response?.data?.message || 'Failed to move to cart'
      return { success: false, message }
    } finally {
      finishLoading()
    }
  }

  return {
    wishlistItems,
    wishlistCount,
    loading,
    isInWishlist,
    isWishlistPending,
    fetchWishlist,
    addToWishlist,
    removeFromWishlist,
    toggleWishlist,
    moveToCart
  }
}
