var tour = new Anno([{
    target: '#wrapper-navbar',
    position: 'bottom',
    content: 'This is the navbar',
    buttons: [AnnoButton.NextButton]
  },
  {
    target : '#sidebar-minimized',
    position: 'right',
    content : 'Click this button to view unit progress',
    buttons: [AnnoButton.NextButton]
  },
  {
    target : '#navigation-links',
    position: 'top',
    content : 'The navigation links help you to move between lessons within the course',
    buttons: [AnnoButton.NextButton]
  }])