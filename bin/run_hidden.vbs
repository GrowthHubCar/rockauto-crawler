' Launch a console program with NO visible window.
' php.exe is a console binary, so a scheduled task running it flashes a window on the
' user's desktop every interval no matter how the task itself is configured. Marking
' the task Hidden does not help, and switching the task principal to a non-interactive
' logon needs elevation. wscript + Run(..., 0, False) hides it without either.
' Usage:  wscript.exe //B //Nologo bin\run_hidden.vbs "<exe>" "<arg1>" ...
Option Explicit
Dim sh, cmd, i
Set sh = CreateObject("WScript.Shell")
If WScript.Arguments.Count = 0 Then WScript.Quit 1
sh.CurrentDirectory = "C:\xampp\htdocs\RockAuto"
cmd = ""
For i = 0 To WScript.Arguments.Count - 1
    cmd = cmd & """" & WScript.Arguments(i) & """"
    If i < WScript.Arguments.Count - 1 Then cmd = cmd & " "
Next
' 0 = hidden window, False = do not wait
sh.Run cmd, 0, False
