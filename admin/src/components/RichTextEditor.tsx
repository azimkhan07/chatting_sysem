import { ActionIcon, Group, ScrollArea, Tooltip } from '@mantine/core'
import {
  IconBold,
  IconItalic,
  IconLink,
  IconList,
  IconListNumbers,
  IconUnderline,
} from '@tabler/icons-react'
import { useRef } from 'react'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
  minHeight?: number
  placeholder?: string
  id?: string
}

function exec(command: string, value?: string) {
  document.execCommand(command, false, value)
}

export function RichTextEditor({
  value,
  onChange,
  minHeight = 200,
  placeholder = 'Write your email body…',
  id,
}: RichTextEditorProps) {
  const ref = useRef<HTMLDivElement>(null)

  const run = (command: string, value?: string) => {
    ref.current?.focus()
    exec(command, value)
    if (ref.current) onChange(ref.current.innerHTML)
  }

  const insertLink = () => {
    const url = window.prompt('Link URL', 'https://')
    if (url) run('createLink', url)
  }

  const tools: Array<{ title: string; icon: React.ReactNode; onClick: () => void }> = [
    { title: 'Bold', icon: <IconBold size={15} />, onClick: () => run('bold') },
    { title: 'Italic', icon: <IconItalic size={15} />, onClick: () => run('italic') },
    { title: 'Underline', icon: <IconUnderline size={15} />, onClick: () => run('underline') },
    { title: 'Bulleted list', icon: <IconList size={15} />, onClick: () => run('insertUnorderedList') },
    { title: 'Numbered list', icon: <IconListNumbers size={15} />, onClick: () => run('insertOrderedList') },
    {
      title: 'Heading',
      icon: (
        <span style={{ fontSize: 11, fontWeight: 700 }}>
          H
        </span>
      ),
      onClick: () => run('formatBlock', 'H3'),
    },
    { title: 'Paragraph', icon: <span style={{ fontSize: 10 }}>¶</span>, onClick: () => run('formatBlock', 'P') },
    { title: 'Link', icon: <IconLink size={15} />, onClick: insertLink },
    { title: 'Clear formatting', icon: <span style={{ fontSize: 10 }}>⌫</span>, onClick: () => exec('removeFormat') },
  ]

  return (
    <div data-richeditor>
      <Group gap={4} mb={6} wrap="wrap">
        {tools.map((t) => (
          <Tooltip key={t.title} label={t.title} withArrow position="bottom">
            <ActionIcon variant="default" size="md" onClick={t.onClick}>
              {t.icon}
            </ActionIcon>
          </Tooltip>
        ))}
      </Group>
      <ScrollArea.Autosize mah={360} mx="auto">
        <div
          ref={ref}
          id={id}
          contentEditable
          suppressContentEditableWarning
          dangerouslySetInnerHTML={{ __html: value }}
          onInput={(e) => onChange((e.target as HTMLDivElement).innerHTML)}
          className="rte-body"
          data-placeholder={placeholder}
          style={{ minHeight, border: '1px solid var(--mantine-color-default-border)' }}
        />
      </ScrollArea.Autosize>
      <style>{`
        .rte-body { padding: 10px 12px; font-size: 13px; line-height: 1.6; border-radius: 6px; outline: none; }
        .rte-body:focus { border-color: var(--mantine-primary-color-filled); }
        .rte-body:empty:before { content: attr(data-placeholder); color: var(--mantine-color-dimmed); }
        .rte-body h3 { font-size: 15px; margin: 8px 0 4px; }
        .rte-body p { margin: 0 0 6px; }
        .rte-body ul, .rte-body ol { padding-left: 20px; }
        .rte-body a { color: var(--mantine-color-blue-6); }
      `}</style>
    </div>
  )
}