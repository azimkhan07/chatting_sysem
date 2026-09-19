export interface Song {
  id: number
  name: string
  artist: string
  url: string
  duration: number | null
  genre: string | null
}

export interface SearchSong {
  name: string
  artist: string
  url: string | null
  genre: string | null
}