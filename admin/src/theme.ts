import { createTheme } from '@mantine/core'

export const theme = createTheme({
  primaryColor: 'blue',
  fontFamily:
    'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  fontSizes: {
    xs: '11px',
    sm: '12.5px',
    md: '13.5px',
    lg: '15px',
    xl: '17px',
  },
  headings: {
    fontWeight: '600',
    sizes: {
      h1: { fontSize: '20px' },
      h2: { fontSize: '18px' },
      h3: { fontSize: '16px' },
      h4: { fontSize: '14px' },
      h5: { fontSize: '13px' },
      h6: { fontSize: '12px' },
    },
  },
  defaultRadius: 'sm',
  components: {
    Table: {
      defaultProps: { verticalSpacing: 'xs', horizontalSpacing: 'sm', fz: 'sm' },
    },
    TextInput: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    Select: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    NumberInput: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    Textarea: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    PasswordInput: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    Button: {
      defaultProps: { size: 'xs', radius: 'sm' },
    },
    Badge: {
      defaultProps: { size: 'sm' },
    },
  },
})